<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Contacto;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Panel de administración (rol `administrador`): métricas globales, gestión de
 * cuentas, verificación de anunciantes, moderación del catálogo, bandeja de
 * contacto y bitácora de auditoría.
 */
class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────
    // Acceso al panel
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Sin token no se entra al panel.
     */
    public function test_el_panel_exige_autenticacion(): void
    {
        $this->getJson('/api/v1/admin/dashboard', $this->cabeceras())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    /**
     * Ningún otro rol accede al panel de administración.
     */
    public function test_otros_roles_no_acceden_al_panel(): void
    {
        foreach ([Role::Inmobiliaria, Role::Vendedor, Role::Cliente] as $rol) {
            $this->autenticar($this->usuario($rol));

            $this->getJson('/api/v1/admin/usuarios', $this->cabeceras())
                ->assertForbidden()
                ->assertJsonPath('code', 'FORBIDDEN');
        }
    }

    /**
     * El registro público nunca crea administradores.
     */
    public function test_el_registro_publico_no_crea_administradores(): void
    {
        $this->postJson('/api/v1/auth/register/administrador', [
            'name' => 'Intruso',
            'email' => 'intruso@demo.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $this->cabeceras())->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * `/auth/me` refleja el rol administrativo (sin perfil extendido ni
     * publicaciones).
     */
    public function test_me_devuelve_el_rol_administrador(): void
    {
        $this->autenticar($this->usuario(Role::Administrador));

        $this->getJson('/api/v1/auth/me', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.role.value', 'administrador')
            ->assertJsonPath('data.usuario.role.label', 'Administrador')
            ->assertJsonPath('data.usuario.perfil', null)
            ->assertJsonPath('data.propiedades_publicadas', 0);
    }

    /**
     * La columna `users.role` acepta el nuevo valor y el perfil queda vacío.
     */
    public function test_la_base_de_datos_acepta_el_rol_administrador(): void
    {
        $usuario = User::factory()->administrador()->create();

        $this->assertSame(Role::Administrador, $usuario->refresh()->role);
        $this->assertTrue($usuario->esAdministrador());
        $this->assertFalse($usuario->publicaPropiedades());
        $this->assertNull($usuario->perfil_type);
        $this->assertNull($usuario->propietario());
    }

    /**
     * El agente consulta su propia cuenta y el mapa de permisos del panel.
     */
    public function test_el_agente_consulta_su_perfil_y_permisos(): void
    {
        $this->autenticar($this->usuario(Role::Administrador));

        $this->getJson('/api/v1/admin/perfil', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.role.value', 'administrador')
            ->assertJsonPath('data.permisos.usuarios.0', 'ver')
            ->assertJsonPath('data.administradores_activos', 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────

    /**
     * El resumen global agrupa cuentas, anunciantes, catálogo y actividad.
     */
    public function test_el_dashboard_devuelve_las_metricas_globales(): void
    {
        $anunciante = $this->usuario(Role::Inmobiliaria);
        Propiedad::factory()->delPropietario($anunciante->propietario())->publicada()->create();
        $this->usuario(Role::Cliente);

        $this->autenticar($this->usuario(Role::Administrador));

        $this->getJson('/api/v1/admin/dashboard', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usuarios.por_rol.administrador', 1)
            ->assertJsonPath('data.usuarios.por_rol.inmobiliaria', 1)
            ->assertJsonPath('data.usuarios.por_rol.cliente', 1)
            ->assertJsonPath('data.inmobiliarias.total', 1)
            ->assertJsonPath('data.propiedades.publicada', 1)
            ->assertJsonPath('data.administradores_activos', 1)
            ->assertJsonStructure([
                'data' => [
                    'usuarios' => ['total', 'activos', 'inactivos', 'nuevos_30_dias', 'por_rol'],
                    'inmobiliarias' => ['total', 'verificadas', 'pendientes_de_verificacion'],
                    'vendedores' => ['total', 'verificados', 'pendientes_de_verificacion'],
                    'clientes' => ['total'],
                    'propiedades' => ['total', 'borrador', 'publicada', 'destacadas'],
                    'actividad' => ['vistas', 'intereses', 'citas', 'mensajes', 'favoritos'],
                    'contactos' => ['total', 'pendientes'],
                    'ingresos' => ['comision_porcentaje', 'por_moneda'],
                    'administradores_activos',
                    'generado_en',
                ],
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Gestión de cuentas
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Listado global con filtro por rol.
     */
    public function test_lista_las_cuentas_filtradas_por_rol(): void
    {
        $this->usuario(Role::Cliente);
        $vendedor = $this->usuario(Role::Vendedor);

        $this->autenticar($this->usuario(Role::Administrador));

        $respuesta = $this->getJson('/api/v1/admin/usuarios?role=vendedor', $this->cabeceras());

        $respuesta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', $vendedor->email)
            ->assertJsonPath('data.0.role.value', 'vendedor')
            ->assertJsonPath('meta.administradores_activos', 1);
    }

    /**
     * Alta de otra cuenta de administrador (sin perfil extendido).
     */
    public function test_crea_una_cuenta_de_administrador(): void
    {
        Notification::fake();
        $this->autenticar($this->usuario(Role::Administrador));

        $respuesta = $this->postJson('/api/v1/admin/usuarios', [
            'role' => 'administrador',
            'name' => 'Nuevo Agente',
            'email' => 'agente2@demo.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+591 70012345',
        ], $this->cabeceras());

        $respuesta->assertCreated()
            ->assertJsonPath('data.usuario.role.value', 'administrador')
            ->assertJsonPath('data.usuario.perfil', null)
            ->assertJsonPath('data.usuario.is_active', true);

        $this->assertDatabaseHas('users', [
            'email' => 'agente2@demo.test',
            'role' => 'administrador',
            'perfil_type' => null,
        ]);
    }

    /**
     * El panel también da de alta anunciantes con su perfil extendido.
     */
    public function test_crea_una_cuenta_de_inmobiliaria_con_su_perfil(): void
    {
        Notification::fake();
        $this->autenticar($this->usuario(Role::Administrador));

        $respuesta = $this->postJson('/api/v1/admin/usuarios', [
            'role' => 'inmobiliaria',
            'name' => 'Responsable Nueva',
            'email' => 'nueva@demo.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'razon_social' => 'Nueva Propiedades S.R.L.',
            'ruc' => '1099887766',
            'ciudad' => 'La Paz',
            'estado_provincia' => 'La Paz',
        ], $this->cabeceras());

        $respuesta->assertCreated()->assertJsonPath('data.usuario.role.value', 'inmobiliaria');

        $this->assertDatabaseHas('users', ['email' => 'nueva@demo.test', 'role' => 'inmobiliaria']);
        $this->assertDatabaseHas('inmobiliarias', ['ruc' => '1099887766']);
    }

    /**
     * La suspensión revoca el acceso de la cuenta intervenida.
     */
    public function test_suspende_una_cuenta_y_bloquea_su_acceso(): void
    {
        $inmobiliaria = $this->usuario(Role::Inmobiliaria);

        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$inmobiliaria->id}/desactivar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.is_active', false);

        $this->assertDatabaseHas('users', ['id' => $inmobiliaria->id, 'is_active' => false]);

        // La cuenta suspendida ya no puede usar su propio panel.
        $this->autenticar($inmobiliaria);

        $this->getJson('/api/v1/inmobiliaria/perfil', $this->cabeceras())
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.cuenta_inactiva'));
    }

    /**
     * La reactivación devuelve el acceso a la cuenta.
     */
    public function test_reactiva_una_cuenta_suspendida(): void
    {
        $cliente = $this->usuario(Role::Cliente);
        $cliente->forceFill(['is_active' => false])->save();

        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$cliente->id}/activar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.is_active', true);

        $this->assertDatabaseHas('users', ['id' => $cliente->id, 'is_active' => true]);
    }

    /**
     * Ningún agente puede intervenir su propia cuenta.
     */
    public function test_un_agente_no_puede_intervenir_su_propia_cuenta(): void
    {
        $admin = $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$admin->id}/desactivar", [], $this->cabeceras())
            ->assertForbidden();

        $this->patchJson("/api/v1/admin/usuarios/{$admin->id}/rol", ['role' => 'cliente'], $this->cabeceras())
            ->assertForbidden();

        $this->deleteJson("/api/v1/admin/usuarios/{$admin->id}", [], $this->cabeceras())
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true, 'role' => 'administrador']);
    }

    /**
     * El mínimo de administradores activos es innegociable.
     */
    public function test_no_se_puede_quedar_por_debajo_del_minimo_de_administradores(): void
    {
        config(['mercado.admin.reglas.minimo_administradores_activos' => 2]);

        $colega = $this->usuario(Role::Administrador);
        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$colega->id}/desactivar", [], $this->cabeceras())
            ->assertStatus(409)
            ->assertJsonPath('code', 'ULTIMO_ADMINISTRADOR');

        $this->deleteJson("/api/v1/admin/usuarios/{$colega->id}", [], $this->cabeceras())
            ->assertStatus(409)
            ->assertJsonPath('code', 'ULTIMO_ADMINISTRADOR');

        $this->assertDatabaseHas('users', ['id' => $colega->id, 'is_active' => true, 'role' => 'administrador']);
    }

    /**
     * Con dos administradores activos sí se puede suspender a uno.
     */
    public function test_suspende_a_un_segundo_administrador(): void
    {
        $colega = $this->usuario(Role::Administrador);
        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$colega->id}/desactivar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.is_active', false);
    }

    /**
     * Cambiar el rol exige un perfil extendido compatible con el rol destino.
     */
    public function test_el_cambio_de_rol_exige_un_perfil_compatible(): void
    {
        $colega = $this->usuario(Role::Administrador);
        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$colega->id}/rol", ['role' => 'cliente'], $this->cabeceras())
            ->assertStatus(409)
            ->assertJsonPath('code', 'PERFIL_INCOMPATIBLE');

        $this->assertDatabaseHas('users', ['id' => $colega->id, 'role' => 'administrador']);
    }

    /**
     * Una cuenta de anunciante puede incorporarse al equipo de administración.
     */
    public function test_promueve_una_inmobiliaria_a_administrador(): void
    {
        $inmobiliaria = $this->usuario(Role::Inmobiliaria);
        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$inmobiliaria->id}/rol", ['role' => 'administrador'], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.role.value', 'administrador');

        $this->assertDatabaseHas('users', ['id' => $inmobiliaria->id, 'role' => 'administrador']);

        // Al cambiar el rol pierde el acceso al panel de inmobiliaria.
        $this->autenticar($inmobiliaria->refresh());

        $this->getJson('/api/v1/inmobiliaria/perfil', $this->cabeceras())->assertForbidden();
    }

    /**
     * Asignar el rol que la cuenta ya tiene es un conflicto, no un cambio.
     */
    public function test_no_puede_asignarse_el_rol_que_la_cuenta_ya_tiene(): void
    {
        $colega = $this->usuario(Role::Administrador);
        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$colega->id}/rol", ['role' => 'administrador'], $this->cabeceras())
            ->assertStatus(409)
            ->assertJsonPath('code', 'ROL_SIN_CAMBIO');
    }

    /**
     * Baja lógica y restauración de una cuenta.
     */
    public function test_da_de_baja_y_restaura_una_cuenta(): void
    {
        $cliente = $this->usuario(Role::Cliente);
        $this->autenticar($this->usuario(Role::Administrador));

        $this->deleteJson("/api/v1/admin/usuarios/{$cliente->id}", [], $this->cabeceras())->assertOk();
        $this->assertSoftDeleted('users', ['id' => $cliente->id]);

        $this->patchJson("/api/v1/admin/usuarios/{$cliente->id}/restaurar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.is_active', true);

        $this->assertDatabaseHas('users', ['id' => $cliente->id, 'deleted_at' => null]);
    }

    /**
     * Edición administrativa de los datos de contacto.
     */
    public function test_edita_los_datos_de_una_cuenta(): void
    {
        $vendedor = $this->usuario(Role::Vendedor);
        $this->autenticar($this->usuario(Role::Administrador));

        $this->patchJson("/api/v1/admin/usuarios/{$vendedor->id}", [
            'name' => 'Nombre Corregido',
            'phone' => '+591 60000001',
        ], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.name', 'Nombre Corregido');

        $this->assertDatabaseHas('users', ['id' => $vendedor->id, 'name' => 'Nombre Corregido']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Anunciantes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Verificación y retiro de la verificación de una inmobiliaria.
     */
    public function test_verifica_una_inmobiliaria(): void
    {
        $perfil = $this->usuario(Role::Inmobiliaria)->perfil;
        $this->autenticar($this->usuario(Role::Administrador));

        $this->postJson("/api/v1/admin/inmobiliarias/{$perfil->id}/verificar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.inmobiliaria.verificado', true);

        $this->assertDatabaseHas('inmobiliarias', ['id' => $perfil->id, 'verificado' => true]);

        $this->deleteJson("/api/v1/admin/inmobiliarias/{$perfil->id}/verificar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.inmobiliaria.verificado', false);
    }

    /**
     * Verificación de un vendedor particular.
     */
    public function test_verifica_un_vendedor(): void
    {
        $perfil = $this->usuario(Role::Vendedor)->perfil;
        $this->autenticar($this->usuario(Role::Administrador));

        $this->postJson("/api/v1/admin/vendedores/{$perfil->id}/verificar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.vendedor.verificado', true);

        $this->assertDatabaseHas('vendedores', ['id' => $perfil->id, 'verificado' => true]);
    }

    /**
     * Listado administrativo de inmobiliarias con filtros.
     */
    public function test_lista_las_inmobiliarias_pendientes_de_verificacion(): void
    {
        $verificada = $this->usuario(Role::Inmobiliaria)->perfil;
        $verificada->forceFill(['verificado' => true])->save();
        $this->usuario(Role::Inmobiliaria);

        $this->autenticar($this->usuario(Role::Administrador));

        $this->getJson('/api/v1/admin/inmobiliarias?verificado=false', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.verificado', false);

        $this->getJson("/api/v1/admin/inmobiliarias/{$verificada->id}", $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.inmobiliaria.verificado', true)
            ->assertJsonStructure(['data' => ['resumen' => ['publicaciones', 'citas', 'interesados']]]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Moderación del catálogo
    // ─────────────────────────────────────────────────────────────────────

    /**
     * El agente modera publicaciones de cualquier anunciante.
     */
    public function test_modera_una_publicacion_ajena(): void
    {
        $anunciante = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($anunciante->propietario())->publicada()->create();

        $this->autenticar($this->usuario(Role::Administrador));

        $this->postJson("/api/v1/admin/propiedades/{$propiedad->id}/destacar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.destacada', true);

        $this->postJson("/api/v1/admin/propiedades/{$propiedad->id}/pausar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'pausada');

        $this->postJson("/api/v1/admin/propiedades/{$propiedad->id}/rechazar", [
            'motivo' => 'Fotografías de otro inmueble',
        ], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'rechazada')
            ->assertJsonPath('data.destacada', false);

        $this->assertDatabaseHas('propiedades', [
            'id' => $propiedad->id,
            'estado' => 'rechazada',
            'destacada' => false,
        ]);
    }

    /**
     * El listado de moderación incluye borradores de todos los anunciantes.
     */
    public function test_lista_todo_el_catalogo_incluidos_los_borradores(): void
    {
        $inmobiliaria = $this->usuario(Role::Inmobiliaria);
        $vendedor = $this->usuario(Role::Vendedor);

        Propiedad::factory()->delPropietario($inmobiliaria->propietario())->publicada()->create();
        $borrador = Propiedad::factory()->delPropietario($vendedor->propietario())->borrador()->create();

        $this->autenticar($this->usuario(Role::Administrador));

        $respuesta = $this->getJson('/api/v1/admin/propiedades', $this->cabeceras());

        $respuesta->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.resumen_por_estado.borrador', 1)
            ->assertJsonPath('meta.resumen_por_estado.publicada', 1)
            ->assertJsonPath('meta.resumen_por_estado.total', 2);

        $this->getJson("/api/v1/admin/propiedades/{$borrador->id}", $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'borrador');
    }

    /**
     * El anunciante no puede moderar las publicaciones de otro anunciante.
     */
    public function test_un_anunciante_no_modera_publicaciones_ajenas(): void
    {
        $anunciante = $this->usuario(Role::Inmobiliaria);
        $otro = $this->usuario(Role::Vendedor);
        $propiedad = Propiedad::factory()->delPropietario($otro->propietario())->publicada()->create();

        $this->autenticar($anunciante);

        $this->postJson("/api/v1/admin/propiedades/{$propiedad->id}/destacar", [], $this->cabeceras())
            ->assertForbidden();
    }

    /**
     * Baja administrativa y restauración de una publicación.
     */
    public function test_da_de_baja_y_restaura_una_publicacion(): void
    {
        Storage::fake('public');
        Storage::fake('private');

        $anunciante = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($anunciante->propietario())->publicada()->create();

        $this->autenticar($this->usuario(Role::Administrador));

        $this->deleteJson("/api/v1/admin/propiedades/{$propiedad->id}", [], $this->cabeceras())->assertOk();
        $this->assertSoftDeleted('propiedades', ['id' => $propiedad->id]);

        $this->patchJson("/api/v1/admin/propiedades/{$propiedad->id}/restaurar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.id', $propiedad->id);

        $this->assertDatabaseHas('propiedades', ['id' => $propiedad->id, 'deleted_at' => null]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bandeja de contacto y auditoría
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Bandeja de mensajes de contacto y marcado como atendido.
     */
    public function test_gestiona_la_bandeja_de_contacto(): void
    {
        $contacto = Contacto::factory()->create();
        Contacto::factory()->atendido()->create();

        $this->autenticar($this->usuario(Role::Administrador));

        $this->getJson('/api/v1/admin/contactos?atendido=false', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pendientes', 1);

        $this->patchJson("/api/v1/admin/contactos/{$contacto->id}/atender", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.atendido', true);

        $this->assertDatabaseHas('contactos', ['id' => $contacto->id, 'atendido' => true]);
    }

    /**
     * Las acciones administrativas quedan en la bitácora de auditoría.
     */
    public function test_registra_la_actividad_administrativa(): void
    {
        $perfil = $this->usuario(Role::Inmobiliaria)->perfil;
        $this->autenticar($this->usuario(Role::Administrador));

        $this->postJson("/api/v1/admin/inmobiliarias/{$perfil->id}/verificar", [], $this->cabeceras())->assertOk();

        $respuesta = $this->getJson('/api/v1/admin/actividad?event=inmobiliaria_verificada', $this->cabeceras());

        $respuesta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.evento', 'inmobiliaria_verificada')
            ->assertJsonPath('data.0.sujeto.tipo', 'inmobiliaria')
            ->assertJsonPath('data.0.sujeto.id', $perfil->id);
    }
}
