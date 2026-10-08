<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoReporte;
use App\Exceptions\BusinessException;
use App\Models\Cliente;
use App\Models\Favorito;
use App\Models\Propiedad;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lista de favoritos del cliente.
 */
final class FavoritoService
{
    public function __construct(
        private readonly ReporteService $reportes,
    ) {
    }

    /**
     * Guarda una propiedad en favoritos (idempotente a nivel de regla: si ya
     * existe devuelve 409 para que el cliente lo sepa explícitamente).
     */
    public function agregar(Cliente $cliente, Propiedad $propiedad, ?string $nota = null): Favorito
    {
        if ($cliente->tieneFavorito($propiedad)) {
            throw BusinessException::conflicto(
                __('messages.favorito_duplicado'),
                ['propiedad' => [__('messages.favorito_duplicado')]],
                'FAVORITO_DUPLICADO',
            );
        }

        return DB::transaction(function () use ($cliente, $propiedad, $nota): Favorito {
            /** @var Favorito $favorito */
            $favorito = $cliente->favoritos()->create([
                'propiedad_id' => $propiedad->getKey(),
                'nota' => $nota,
            ]);

            $propiedad->increment('favoritos_count');

            $this->reportes->registrar(TipoReporte::Favorito, $propiedad, 1, $cliente);

            return $favorito;
        });
    }

    /**
     * Elimina una propiedad de favoritos.
     */
    public function quitar(Cliente $cliente, Propiedad $propiedad): void
    {
        $favorito = $cliente->favoritos()->where('propiedad_id', $propiedad->getKey())->first();

        if ($favorito === null) {
            throw new BusinessException(__('messages.favorito_no_existe'), [], 404, 'FAVORITO_NO_ENCONTRADO');
        }

        DB::transaction(function () use ($favorito, $propiedad): void {
            $favorito->delete();

            if ((int) $propiedad->favoritos_count > 0) {
                $propiedad->decrement('favoritos_count');
            }
        });
    }

    /**
     * Listado paginado de favoritos con la propiedad cargada.
     */
    public function listar(Cliente $cliente, int $porPagina = 15): LengthAwarePaginator
    {
        return $cliente->favoritos()
            ->whereHas('propiedad')
            ->with([
                'propiedad' => static fn ($query) => $query->with(['fotoPrincipal', 'amenidades']),
            ])
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
