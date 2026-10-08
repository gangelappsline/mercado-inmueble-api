<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoInteres;
use App\Enums\EstadoPropiedad;
use App\Enums\FormatoExportacion;
use App\Enums\TipoReporte;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\Reporte;
use App\Support\ExcelSimple;
use App\Support\PdfSimple;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Métricas del negocio y exportación de reportes.
 *
 * Las métricas por propiedad se guardan agregadas por día en la tabla
 * `reportes` (upsert atómico) para que los dashboards sean consultas baratas.
 */
final class ReporteService
{
    /**
     * Registra una métrica del día (vistas, contactos, favoritos, citas...).
     *
     * El registro se incrementa con un upsert y un lock para que dos peticiones
     * simultáneas no se pisen el contador.
     */
    public function registrar(
        TipoReporte $tipo,
        Propiedad $propiedad,
        int $cantidad = 1,
        ?Cliente $cliente = null,
        ?float $valor = null,
        array $metadata = [],
        ?string $fecha = null,
    ): Reporte {
        $fecha ??= now()->toDateString();

        return DB::transaction(function () use ($tipo, $propiedad, $cantidad, $cliente, $valor, $metadata, $fecha): Reporte {
            $reporte = Reporte::query()
                ->where('propiedad_id', $propiedad->getKey())
                ->where('tipo', $tipo->value)
                ->whereDate('fecha', $fecha)
                ->lockForUpdate()
                ->first();

            if ($reporte === null) {
                $reporte = new Reporte([
                    'propiedad_id' => $propiedad->getKey(),
                    'tipo' => $tipo,
                    'fecha' => $fecha,
                    'cantidad' => 0,
                ]);
            }

            $reporte->propietario_type = $propiedad->propietario_type;
            $reporte->propietario_id = $propiedad->propietario_id;
            $reporte->cliente_id = $cliente?->getKey() ?? $reporte->cliente_id;
            $reporte->cantidad = (int) $reporte->cantidad + $cantidad;

            if ($valor !== null) {
                $reporte->valor = $valor;
            }

            if ($metadata !== []) {
                $reporte->metadata = array_merge((array) $reporte->metadata, $metadata);
            }

            $reporte->save();

            return $reporte;
        });
    }

    /**
     * Resumen general del panel: publicaciones, métricas y embudo comercial.
     *
     * @return array<string, mixed>
     */
    public function resumen(Model $propietario, ?string $desde = null, ?string $hasta = null): array
    {
        $rango = $this->rango($desde, $hasta);
        $anterior = $this->rangoAnterior($rango['desde'], $rango['hasta']);

        $metricas = $this->sumarMetricas($propietario, $rango['desde'], $rango['hasta']);
        $metricasAnteriores = $this->sumarMetricas($propietario, $anterior['desde'], $anterior['hasta']);

        $propiedades = Propiedad::query()
            ->delPropietario($propietario)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $interesados = Interes::query()
            ->delPropietario($propietario)
            ->whereBetween('intereses.created_at', [$rango['desde'].' 00:00:00', $rango['hasta'].' 23:59:59'])
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $citas = Cita::query()
            ->delPropietario($propietario)
            ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
            ->count();

        $totalPropiedades = (int) $propiedades->sum();
        $contactos = $metricas['contacto'] ?? 0;
        $conversiones = (int) ($interesados[EstadoInteres::Cerrado->value] ?? 0);

        return [
            'periodo' => $rango,
            'propiedades' => [
                'total' => $totalPropiedades,
                'publicadas' => (int) ($propiedades[EstadoPropiedad::Publicada->value] ?? 0),
                'borradores' => (int) ($propiedades[EstadoPropiedad::Borrador->value] ?? 0),
                'pausadas' => (int) ($propiedades[EstadoPropiedad::Pausada->value] ?? 0),
                'cerradas' => (int) ($propiedades[EstadoPropiedad::Vendida->value] ?? 0)
                    + (int) ($propiedades[EstadoPropiedad::Alquilada->value] ?? 0),
            ],
            'metricas' => [
                'vistas' => $metricas['vista'] ?? 0,
                'contactos' => $contactos,
                'favoritos' => $metricas['favorito'] ?? 0,
                'citas' => $citas,
                'conversiones' => $conversiones,
            ],
            'interesados' => [
                'nuevos' => (int) ($interesados[EstadoInteres::Nuevo->value] ?? 0),
                'en_revision' => (int) ($interesados[EstadoInteres::EnRevision->value] ?? 0),
                'atendidos' => (int) ($interesados[EstadoInteres::Atendido->value] ?? 0),
                'cerrados' => $conversiones,
                'descartados' => (int) ($interesados[EstadoInteres::Descartado->value] ?? 0),
            ],
            'tasas' => [
                'contacto_por_vista' => $this->tasa($contactos, $metricas['vista'] ?? 0),
                'cita_por_contacto' => $this->tasa($citas, $contactos),
                'conversion' => $this->tasa($conversiones, max($contactos, 1)),
            ],
            'comparacion' => [
                'vistas_periodo_anterior' => $metricasAnteriores['vista'] ?? 0,
                'variacion_vistas_porcentaje' => $this->variacion($metricas['vista'] ?? 0, $metricasAnteriores['vista'] ?? 0),
            ],
        ];
    }

    /**
     * Propiedades más vistas del período (desde los agregados diarios).
     *
     * @return Collection<int, Propiedad>
     */
    public function propiedadesMasVistas(Model $propietario, int $limite = 10, ?string $desde = null, ?string $hasta = null): Collection
    {
        $rango = $this->rango($desde, $hasta);

        $totales = Reporte::query()
            ->delPropietario($propietario)
            ->porTipo(TipoReporte::Vista)
            ->enRango($rango['desde'], $rango['hasta'])
            ->whereNotNull('propiedad_id')
            ->selectRaw('propiedad_id, SUM(cantidad) as vistas')
            ->groupBy('propiedad_id')
            ->orderByDesc('vistas')
            ->limit($limite)
            ->pluck('vistas', 'propiedad_id');

        if ($totales->isEmpty()) {
            return new Collection;
        }

        return Propiedad::query()
            ->whereIn('id', $totales->keys())
            ->with(['fotoPrincipal'])
            ->get()
            ->sortByDesc(static fn (Propiedad $propiedad): int => (int) $totales[$propiedad->getKey()])
            ->values()
            ->each(static function (Propiedad $propiedad) use ($totales): void {
                $propiedad->setAttribute('vistas_periodo', (int) $totales[$propiedad->getKey()]);
            });
    }

    /**
     * Embudo de conversión del período.
     *
     * @return array<string, mixed>
     */
    public function conversion(Model $propietario, ?string $desde = null, ?string $hasta = null): array
    {
        $rango = $this->rango($desde, $hasta);

        $metricas = $this->sumarMetricas($propietario, $rango['desde'], $rango['hasta']);

        $interesados = Interes::query()
            ->delPropietario($propietario)
            ->whereBetween('intereses.created_at', [$rango['desde'].' 00:00:00', $rango['hasta'].' 23:59:59'])
            ->count();

        $citas = Cita::query()
            ->delPropietario($propietario)
            ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
            ->count();

        $citasCompletadas = Cita::query()
            ->delPropietario($propietario)
            ->whereBetween('fecha', [$rango['desde'], $rango['hasta']])
            ->where('estado', 'completada')
            ->count();

        $conversiones = Interes::query()
            ->delPropietario($propietario)
            ->where('estado', EstadoInteres::Cerrado->value)
            ->whereBetween('intereses.created_at', [$rango['desde'].' 00:00:00', $rango['hasta'].' 23:59:59'])
            ->count();

        $vistas = $metricas['vista'] ?? 0;

        return [
            'periodo' => $rango,
            'embudo' => [
                'vistas' => $vistas,
                'favoritos' => $metricas['favorito'] ?? 0,
                'contactos' => $interesados,
                'citas' => $citas,
                'citas_completadas' => $citasCompletadas,
                'conversiones' => $conversiones,
            ],
            'tasas' => [
                'favorito_por_vista' => $this->tasa($metricas['favorito'] ?? 0, $vistas),
                'contacto_por_vista' => $this->tasa($interesados, $vistas),
                'cita_por_contacto' => $this->tasa($citas, $interesados),
                'conversion_por_contacto' => $this->tasa($conversiones, $interesados),
                'conversion_por_vista' => $this->tasa($conversiones, $vistas),
            ],
        ];
    }

    /**
     * Ingresos (operaciones cerradas) agrupados por mes y moneda.
     *
     * @return array<string, mixed>
     */
    public function ingresos(Model $propietario, ?string $desde = null, ?string $hasta = null): array
    {
        $rango = $this->rango($desde, $hasta, 365);
        $comision = (float) config('mercado.comision_porcentaje', 3.0);

        $propiedades = Propiedad::query()
            ->delPropietario($propietario)
            ->whereIn('estado', [EstadoPropiedad::Vendida->value, EstadoPropiedad::Alquilada->value])
            ->whereRaw('COALESCE(vendida_en, updated_at) BETWEEN ? AND ?', [
                $rango['desde'].' 00:00:00',
                $rango['hasta'].' 23:59:59',
            ])
            ->with(['fotoPrincipal'])
            ->orderByDesc('vendida_en')
            ->get();

        $porMoneda = $propiedades
            ->groupBy(static fn (Propiedad $propiedad): string => $propiedad->moneda->value)
            ->map(static fn (Collection $grupo): array => [
                'operaciones' => $grupo->count(),
                'monto_total' => round((float) $grupo->sum(static fn (Propiedad $p): float => (float) $p->precio), 2),
                'ticket_promedio' => round((float) $grupo->avg(static fn (Propiedad $p): float => (float) $p->precio), 2),
                'comision_estimada' => round((float) $grupo->sum(static fn (Propiedad $p): float => (float) $p->precio) * $comision / 100, 2),
            ]);

        $porMes = $propiedades
            ->groupBy(static fn (Propiedad $propiedad): string => Carbon::parse($propiedad->vendida_en ?? $propiedad->updated_at)->format('Y-m'))
            ->map(static fn (Collection $grupo, string $mes): array => [
                'mes' => $mes,
                'operaciones' => $grupo->count(),
                'monto_total' => round((float) $grupo->sum(static fn (Propiedad $p): float => (float) $p->precio), 2),
            ])
            ->values();

        return [
            'periodo' => $rango,
            'comision_porcentaje' => $comision,
            'resumen_por_moneda' => $porMoneda,
            'evolucion_mensual' => $porMes,
            'detalle' => $propiedades->map(static fn (Propiedad $propiedad): array => [
                'id' => $propiedad->getKey(),
                'codigo' => $propiedad->codigo,
                'titulo' => $propiedad->titulo,
                'operacion' => $propiedad->operacion->value,
                'estado' => $propiedad->estado->value,
                'precio' => (float) $propiedad->precio,
                'moneda' => $propiedad->moneda->value,
                'ciudad' => $propiedad->ciudad,
                'cerrada_en' => optional($propiedad->vendida_en ?? $propiedad->updated_at)?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * Genera la descarga del reporte en el formato solicitado.
     *
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, string|int|float|null>>  $filas
     */
    public function exportar(
        FormatoExportacion $formato,
        string $titulo,
        array $encabezados,
        array $filas,
    ): Response {
        $nombre = Str::slug($titulo).'-'.now()->format('Ymd-His');

        return match ($formato) {
            FormatoExportacion::Pdf => response(
                PdfSimple::generar($titulo, $this->lineasParaPdf($encabezados, $filas), config('app.name')),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => sprintf('attachment; filename="%s.pdf"', $nombre),
                ],
            ),
            FormatoExportacion::Excel => response(
                ExcelSimple::generar($encabezados, $filas, $titulo),
                200,
                [
                    'Content-Type' => $formato->mime(),
                    'Content-Disposition' => sprintf('attachment; filename="%s.%s"', $nombre, $formato->extension()),
                ],
            ),
            FormatoExportacion::Csv => response(
                ExcelSimple::csv($encabezados, $filas),
                200,
                [
                    'Content-Type' => $formato->mime(),
                    'Content-Disposition' => sprintf('attachment; filename="%s.%s"', $nombre, $formato->extension()),
                ],
            ),
        };
    }

    /**
     * Normaliza el rango de fechas (por defecto últimos 30 días).
     *
     * @return array{desde: string, hasta: string}
     */
    public function rango(?string $desde, ?string $hasta, int $diasPorDefecto = 30): array
    {
        $fin = $hasta !== null ? Carbon::parse($hasta)->endOfDay() : now();
        $inicio = $desde !== null
            ? Carbon::parse($desde)->startOfDay()
            : $fin->copy()->subDays($diasPorDefecto)->startOfDay();

        return [
            'desde' => $inicio->toDateString(),
            'hasta' => $fin->toDateString(),
        ];
    }

    /**
     * Período inmediatamente anterior de la misma longitud (para comparativas).
     *
     * @return array{desde: string, hasta: string}
     */
    private function rangoAnterior(string $desde, string $hasta): array
    {
        $inicio = Carbon::parse($desde);
        $fin = Carbon::parse($hasta);
        $dias = $inicio->diffInDays($fin) + 1;

        return [
            'desde' => $inicio->copy()->subDays($dias)->toDateString(),
            'hasta' => $inicio->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * Suma de métricas agrupadas por tipo en un período.
     *
     * @return array<string, int>
     */
    private function sumarMetricas(Model $propietario, string $desde, string $hasta): array
    {
        return Reporte::query()
            ->delPropietario($propietario)
            ->enRango($desde, $hasta)
            ->selectRaw('tipo, SUM(cantidad) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Porcentaje con dos decimales.
     */
    private function tasa(int|float $parte, int|float $total): float
    {
        return $total > 0 ? round(($parte / $total) * 100, 2) : 0.0;
    }

    /**
     * Variación porcentual entre dos períodos.
     */
    private function variacion(int|float $actual, int|float $anterior): float
    {
        return $anterior > 0
            ? round((($actual - $anterior) / $anterior) * 100, 2)
            : ($actual > 0 ? 100.0 : 0.0);
    }

    /**
     * Convierte las filas tabulares en líneas de texto para el PDF.
     *
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, string|int|float|null>>  $filas
     * @return array<int, string>
     */
    private function lineasParaPdf(array $encabezados, array $filas): array
    {
        $columnas = count($encabezados);
        $anchos = [];

        foreach ($encabezados as $indice => $encabezado) {
            $ancho = mb_strlen($encabezado);

            foreach ($filas as $fila) {
                $ancho = max($ancho, mb_strlen((string) ($fila[$indice] ?? '')));
            }

            $anchos[$indice] = min($ancho, 28);
        }

        $formatear = static function (array $valores) use ($anchos, $columnas): string {
            $celdas = [];

            for ($i = 0; $i < $columnas; $i++) {
                $celdas[] = str_pad(mb_substr((string) ($valores[$i] ?? ''), 0, $anchos[$i]), $anchos[$i]);
            }

            return implode('  ', $celdas);
        };

        $lineas = [$formatear($encabezados), str_repeat('-', array_sum($anchos) + 2 * max($columnas - 1, 0))];

        foreach ($filas as $fila) {
            $lineas[] = $formatear($fila);
        }

        $lineas[] = '';
        $lineas[] = sprintf('Total de registros: %d', count($filas));

        return $lineas;
    }
}
