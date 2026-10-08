<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Role;
use PHPUnit\Framework\TestCase;

/**
 * Contratos del catálogo de roles, incluido el rol `administrador`.
 *
 * El orden de `Role::valores()` es relevante: la columna `users.role` es un
 * ENUM y los valores nuevos deben agregarse al final para que las migraciones
 * no reconstruyan la tabla (MySQL).
 */
class RoleTest extends TestCase
{
    public function test_el_catalogo_incluye_el_rol_administrador_al_final(): void
    {
        $valores = Role::valores();

        $this->assertSame(
            ['inmobiliaria', 'vendedor', 'cliente', 'administrador'],
            $valores,
        );
        $this->assertSame('administrador', Role::Administrador->value);
        $this->assertSame('Administrador', Role::Administrador->label());
        $this->assertSame('administrador', Role::Administrador->scope());
    }

    public function test_el_administrador_no_publica_propiedades(): void
    {
        $this->assertFalse(Role::Administrador->publicaPropiedades());
        $this->assertFalse(Role::Cliente->publicaPropiedades());
        $this->assertTrue(Role::Inmobiliaria->publicaPropiedades());
        $this->assertTrue(Role::Vendedor->publicaPropiedades());
    }

    public function test_solo_inmobiliaria_y_vendedor_son_anunciantes(): void
    {
        $this->assertTrue(Role::Inmobiliaria->esAnunciante());
        $this->assertTrue(Role::Vendedor->esAnunciante());
        $this->assertFalse(Role::Cliente->esAnunciante());
        $this->assertFalse(Role::Administrador->esAnunciante());
    }

    public function test_el_administrador_no_admite_registro_publico(): void
    {
        $this->assertFalse(Role::Administrador->permiteRegistroPublico());
        $this->assertSame(
            ['inmobiliaria', 'vendedor', 'cliente'],
            Role::valoresRegistrables(),
        );
        $this->assertSame(
            ['inmobiliaria' => 'Inmobiliaria', 'vendedor' => 'Vendedor', 'cliente' => 'Cliente'],
            Role::opcionesRegistrables(),
        );
    }

    public function test_el_administrador_no_participa_en_los_hilos_de_mensajes(): void
    {
        $this->assertFalse(Role::Administrador->participaEnMensajes());
        $this->assertSame(
            ['inmobiliaria', 'vendedor', 'cliente'],
            Role::valoresParticipantes(),
        );
    }

    public function test_las_opciones_exponen_los_cuatro_roles(): void
    {
        $this->assertSame([
            'inmobiliaria' => 'Inmobiliaria',
            'vendedor' => 'Vendedor',
            'cliente' => 'Cliente',
            'administrador' => 'Administrador',
        ], Role::opciones());
    }
}
