<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base de todos los Form Requests de la API.
 *
 * - `authorize()` por defecto permite el acceso: la autorización se resuelve
 *   con middleware (`role:...`) y policies (más expresivas por recurso).
 * - Los errores de validación se devuelven con el sobre uniforme
 *   `{success:false,message,code,errors}` en lugar del formato por defecto.
 * - Los mensajes en español viven en `lang/es/validation.php`.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Por defecto los requests de la API están autorizados; cada controlador
     * aplica la autorización fina con `$this->authorize(...)`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Respuesta JSON uniforme ante errores de validación (HTTP 422).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('messages.datos_invalidos'),
                422,
                'VALIDATION_ERROR',
                $validator->errors()->toArray(),
            )
        );
    }

    /**
     * Indica si el request pide publicar el recurso inmediatamente.
     */
    public function publicarAlCrear(): bool
    {
        return filter_var($this->input('publicar', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Devuelve sólo los campos validados que no estén vacíos, respetando el
     * subconjunto de claves indicado.
     *
     * @param  array<int, string>  $claves
     * @return array<string, mixed>
     */
    public function datosValidados(array $claves): array
    {
        $validados = $this->validated();

        return collect($claves)
            ->filter(static fn (string $clave): bool => array_key_exists($clave, $validados))
            ->mapWithKeys(static fn (string $clave): array => [$clave => $validados[$clave]])
            ->all();
    }

    /**
     * Cantidad de elementos por página solicitada (con topes de seguridad).
     */
    public function porPagina(int $porDefecto = 15): int
    {
        $solicitado = (int) $this->integer('per_page', $porDefecto);
        $maximo = (int) config('mercado.paginacion.maxima', 100);

        return max(1, min($solicitado ?: $porDefecto, $maximo));
    }
}
