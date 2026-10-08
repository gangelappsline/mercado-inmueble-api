<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Propiedad;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contrato del repositorio de propiedades.
 *
 * Concentra las consultas complejas (filtros avanzados, geolocalización y
 * eager loading) para que los servicios y controladores no construyan SQL.
 */
interface PropiedadRepositoryInterface
{
    /**
     * Catálogo público paginado con filtros avanzados.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Propiedad>
     */
    public function catalogar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator;

    /**
     * Publicaciones de un anunciante (panel inmobiliaria/vendedor).
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Propiedad>
     */
    public function listarParaPropietario(Model $propietario, array $filtros = [], int $porPagina = 15): LengthAwarePaginator;

    /**
     * Últimas publicaciones destacadas/publicadas para la portada.
     *
     * @return Collection<int, Propiedad>
     */
    public function destacadas(int $limite = 8): Collection;

    /**
     * Carga las relaciones y contadores del detalle de una propiedad.
     */
    public function detalle(Propiedad $propiedad): Propiedad;

    /**
     * Propiedades similares (misma ciudad, tipo y operación, rango de precio).
     *
     * @return Collection<int, Propiedad>
     */
    public function similares(Propiedad $propiedad, int $limite = 6): Collection;

    /**
     * Resumen de publicaciones por estado para un anunciante.
     *
     * @return array<string, int>
     */
    public function resumenPorEstado(Model $propietario): array;

    /**
     * Ciudades distintas con publicaciones (usado por GET /ciudades).
     *
     * @return Collection<int, Propiedad>
     */
    public function ciudadesConConteo(): Collection;
}
