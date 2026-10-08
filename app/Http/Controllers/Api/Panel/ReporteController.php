<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Panel\Concerns\ResuelvePropietario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ExportarReporteRequest;
use App\Http\Requests\Reporte\ReporteRangoRequest;
use App\Services\ReporteService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reportes del panel: resumen, propiedades más vistas, conversión e ingresos,
 * con exportación a PDF, Excel o CSV.
 */
class ReporteController extends Controller
{
    use ResuelvePropietario;

    public function __construct(
        private readonly ReporteService $reportes,
    ) {
    }

    /**
     * Resumen general del periodo (por defecto, últimos 30 días).
     */
    public function resumen(ReporteRangoRequest $request): JsonResponse
    {
        [$desde, $hasta] = $request->fechas();

        return ApiResponse::success(
            $this->reportes->resumen($this->propietario($request), $desde, $hasta),
            __('messages.reporte_generado'),
        );
    }

    /**
     * Ranking de propiedades por vistas.
     */
    public function propiedadesMasVistas(ReporteRangoRequest $request): JsonResponse
    {
        [$desde, $hasta] = $request->fechas();

        $propiedades = $this->reportes->propiedadesMasVistas(
            $this->propietario($request),
            (int) ($request->validated('limite') ?? 10),
            $desde,
            $hasta,
        );

        $ranking = $propiedades->map(static fn (\App\Models\Propiedad $propiedad): array => [
            'id' => $propiedad->getKey(),
            'codigo' => $propiedad->codigo,
            'titulo' => $propiedad->titulo,
            'ciudad' => $propiedad->ciudad,
            'moneda' => $propiedad->moneda->value,
            'precio' => (float) $propiedad->precio,
            'vistas_totales' => (int) $propiedad->vistas_count,
            'vistas_periodo' => (int) $propiedad->vistas_periodo,
            'foto_principal' => $propiedad->fotoPrincipal?->url,
        ])->values();

        return ApiResponse::success($ranking, __('messages.reporte_generado'));
    }

    /**
     * Embudo de conversión del periodo.
     */
    public function conversion(ReporteRangoRequest $request): JsonResponse
    {
        [$desde, $hasta] = $request->fechas();

        return ApiResponse::success(
            $this->reportes->conversion($this->propietario($request), $desde, $hasta),
            __('messages.reporte_generado'),
        );
    }

    /**
     * Ingresos estimados por comisión y operaciones cerradas.
     */
    public function ingresos(ReporteRangoRequest $request): JsonResponse
    {
        [$desde, $hasta] = $request->fechas();

        return ApiResponse::success(
            $this->reportes->ingresos($this->propietario($request), $desde, $hasta),
            __('messages.reporte_generado'),
        );
    }

    /**
     * Exporta el reporte solicitado como descarga (PDF, Excel o CSV).
     */
    public function exportar(ExportarReporteRequest $request): Response
    {
        $propietario = $this->propietario($request);

        [$titulo, $encabezados, $filas] = $this->datosDeExportacion(
            $request->reporte(),
            $propietario,
            $request->validated('desde'),
            $request->validated('hasta'),
        );

        return $this->reportes->exportar($request->formato(), $titulo, $encabezados, $filas);
    }

    /**
     * Construye título, encabezados y filas del reporte a exportar.
     *
     * @return array{0: string, 1: array<int, string>, 2: array<int, array<int, string|int|float|null>>}
     */
    private function datosDeExportacion(string $reporte, Model $propietario, ?string $desde, ?string $hasta): array
    {
        $datos = match ($reporte) {
            'propiedades-mas-vistas' => $this->reportes->propiedadesMasVistas($propietario, 20, $desde, $hasta)->toArray(),
            'conversion' => $this->reportes->conversion($propietario, $desde, $hasta),
            'ingresos' => $this->reportes->ingresos($propietario, $desde, $hasta),
            default => $this->reportes->resumen($propietario, $desde, $hasta),
        };

        $titulo = sprintf('%s — %s', config('app.name'), $reporte);

        return [$titulo, ...$this->tabular($datos)];
    }

    /**
     * Convierte la estructura devuelta por el servicio en filas tabulares.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function tabular(mixed $datos): array
    {
        $datos = json_decode((string) json_encode($datos), true);

        if (is_array($datos) && $datos !== [] && array_is_list($datos) && is_array($datos[0] ?? null)) {
            $encabezados = array_keys($datos[0]);
            $filas = array_map(
                fn (array $fila): array => array_map($this->aTexto(...), array_values($fila)),
                $datos,
            );

            return [$encabezados, $filas];
        }

        $plano = Arr::dot(is_array($datos) ? $datos : []);
        $filas = [];

        foreach ($plano as $clave => $valor) {
            $filas[] = [$clave, $this->aTexto($valor)];
        }

        return [['Métrica', 'Valor'], $filas];
    }

    /**
     * Representa cualquier valor escalar como texto para la exportación.
     */
    private function aTexto(mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'sí' : 'no',
            is_scalar($valor) => (string) $valor,
            default => (string) json_encode($valor, JSON_UNESCAPED_UNICODE),
        };
    }
}
