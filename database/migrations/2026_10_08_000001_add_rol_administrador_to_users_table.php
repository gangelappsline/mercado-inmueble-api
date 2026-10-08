<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Agrega el valor `administrador` al ENUM de la columna `users.role` en las
 * bases de datos ya migradas.
 *
 * Las instalaciones nuevas no la necesitan: `0001_01_01_000000_create_users_table.php`
 * construye la columna con `Role::valores()`, que ya incluye el nuevo rol. Por
 * eso la migración es **idempotente** (comprueba la definición actual antes de
 * tocarla) e independiente del motor:
 *
 *  - MySQL/MariaDB: `ALTER TABLE ... MODIFY role ENUM(...)`. El valor se agrega
 *    al final del listado, de modo que los registros existentes conservan su
 *    posición y no se reconstruye la tabla ni se pierden los índices.
 *  - PostgreSQL: recrea la restricción `CHECK` que genera el tipo `enum`.
 *  - SQLite/SQL Server: delega en el `change()` nativo de Laravel. Si el motor
 *    no lo permite se registra una advertencia (en desarrollo basta con
 *    `php artisan migrate:fresh`).
 */
return new class extends Migration
{
    private const TABLA = 'users';

    private const COLUMNA = 'role';

    /**
     * Valor agregado al catálogo de roles.
     */
    private const NUEVO_VALOR = 'administrador';

    public function up(): void
    {
        $this->sincronizar(Role::valores(), agregar: true);
    }

    public function down(): void
    {
        $existentes = DB::table(self::TABLA)
            ->where(self::COLUMNA, self::NUEVO_VALOR)
            ->count();

        if ($existentes > 0) {
            Log::warning(sprintf(
                'No se retira el rol "%s" de %s.%s: existen %d cuenta(s) con ese rol. Reasígnalas antes de revertir la migración.',
                self::NUEVO_VALOR,
                self::TABLA,
                self::COLUMNA,
                $existentes,
            ));

            return;
        }

        $this->sincronizar($this->valoresSinAdministrador(), agregar: false);
    }

    /**
     * Ajusta la definición de la columna al catálogo de roles indicado.
     *
     * @param  array<int, string>  $valores
     */
    private function sincronizar(array $valores, bool $agregar): void
    {
        if (! Schema::hasTable(self::TABLA) || ! Schema::hasColumn(self::TABLA, self::COLUMNA)) {
            return;
        }

        match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => $this->sincronizarMysql($valores, $agregar),
            'pgsql' => $this->sincronizarPostgres($valores, $agregar),
            default => $this->sincronizarConSchemaBuilder($valores, $agregar),
        };
    }

    /**
     * MySQL/MariaDB: la columna es un `ENUM` nativo.
     *
     * @param  array<int, string>  $valores
     */
    private function sincronizarMysql(array $valores, bool $agregar): void
    {
        $columna = DB::selectOne(
            'SELECT COLUMN_TYPE AS tipo, IS_NULLABLE AS nullable
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::TABLA, self::COLUMNA],
        );

        if ($columna === null || $this->enEstadoDeseado((string) $columna->tipo, $agregar)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` ENUM(%s) %s COMMENT \'%s\'',
            self::TABLA,
            self::COLUMNA,
            $this->listaEnum($valores),
            strtoupper((string) $columna->nullable) === 'YES' ? 'NULL' : 'NOT NULL',
            str_replace("'", "''", implode(' | ', $valores)),
        ));
    }

    /**
     * PostgreSQL: el tipo `enum` de Laravel se materializa como
     * `varchar(255) CHECK ("role" IN (...))`.
     *
     * @param  array<int, string>  $valores
     */
    private function sincronizarPostgres(array $valores, bool $agregar): void
    {
        $restriccion = DB::selectOne(
            'SELECT con.conname AS nombre, pg_get_constraintdef(con.oid) AS definicion
               FROM pg_constraint con
               JOIN pg_class rel ON rel.oid = con.conrelid
               JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
              WHERE nsp.nspname = current_schema()
                AND rel.relname = ?
                AND con.contype = \'c\'
                AND pg_get_constraintdef(con.oid) LIKE ? ',
            [self::TABLA, '%'.self::COLUMNA.'%'],
        );

        if ($restriccion === null || $this->enEstadoDeseado((string) $restriccion->definicion, $agregar)) {
            return;
        }

        $nombre = (string) $restriccion->nombre;

        DB::statement(sprintf('ALTER TABLE "%s" DROP CONSTRAINT "%s"', self::TABLA, $nombre));
        DB::statement(sprintf(
            'ALTER TABLE "%s" ADD CONSTRAINT "%s" CHECK ("%s" IN (%s))',
            self::TABLA,
            $nombre,
            self::COLUMNA,
            $this->listaEnum($valores),
        ));
    }

    /**
     * Motores donde la restricción viaja en el DDL de la tabla (SQLite) o se
     * gestiona con el schema builder (SQL Server).
     *
     * @param  array<int, string>  $valores
     */
    private function sincronizarConSchemaBuilder(array $valores, bool $agregar): void
    {
        if ($this->definicionSqliteEnEstadoDeseado($agregar)) {
            return;
        }

        try {
            Schema::table(self::TABLA, function (Blueprint $table) use ($valores): void {
                $table->enum(self::COLUMNA, $valores)->change();
            });
        } catch (Throwable $e) {
            Log::warning(sprintf(
                'No se pudo actualizar %s.%s (%s). En entornos de desarrollo recrea el esquema con `php artisan migrate:fresh`. Detalle: %s',
                self::TABLA,
                self::COLUMNA,
                DB::connection()->getDriverName(),
                $e->getMessage(),
            ));
        }
    }

    /**
     * En SQLite la lista de valores vive en el `CREATE TABLE` original; si ya
     * coincide con el estado buscado no se toca la tabla.
     */
    private function definicionSqliteEnEstadoDeseado(bool $agregar): bool
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return false;
        }

        $fila = DB::selectOne(
            "SELECT sql AS ddl FROM sqlite_master WHERE type = 'table' AND name = ?",
            [self::TABLA],
        );

        $ddl = (string) ($fila->ddl ?? '');

        // Sin DDL inspeccionable no hay nada que sincronizar.
        return $ddl === '' || $this->enEstadoDeseado($ddl, $agregar);
    }

    /**
     * ¿La definición actual ya refleja el alta (o la baja) del nuevo valor?
     */
    private function enEstadoDeseado(string $definicion, bool $agregar): bool
    {
        return str_contains($definicion, "'".self::NUEVO_VALOR."'") === $agregar;
    }

    /**
     * Lista SQL de valores: `'a', 'b', 'c'`.
     *
     * @param  array<int, string>  $valores
     */
    private function listaEnum(array $valores): string
    {
        return implode(', ', array_map(
            static fn (string $valor): string => "'".str_replace("'", "''", $valor)."'",
            $valores,
        ));
    }

    /**
     * Catálogo de roles vigente antes de incorporar `administrador`.
     *
     * @return array<int, string>
     */
    private function valoresSinAdministrador(): array
    {
        return array_values(array_filter(
            Role::valores(),
            static fn (string $valor): bool => $valor !== self::NUEVO_VALOR,
        ));
    }
};
