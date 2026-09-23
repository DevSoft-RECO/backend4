<?php

namespace App\Jobs;

use App\Mail\SolicitudAsignada;
use App\Models\Solicitud;
use App\Http\Controllers\SmsController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotifySolicitudAsignadaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $solicitud;

    /**
     * Create a new job instance.
     *
     * @param Solicitud $solicitud
     */
    public function __construct(Solicitud $solicitud)
    {
        $this->solicitud = $solicitud;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("NotifySolicitudAsignadaJob: Procesando notificación para Solicitud #" . $this->solicitud->id);

        // Asegurar que las relaciones estén cargadas
        $this->solicitud->load('responsable');
        $solicitud = $this->solicitud;

        if (!$solicitud->responsable) {
            Log::error("NotifySolicitudAsignadaJob: No se encontró el responsable para la solicitud #" . $solicitud->id);
            return;
        }

        if ($solicitud->responsable_tipo === 'interno') {
            // 1. Envío de Correo para Atención Interna
            if ($solicitud->responsable->email) {
                try {
                    Mail::to($solicitud->responsable->email)->send(new SolicitudAsignada($solicitud));
                    Log::info("NotifySolicitudAsignadaJob: Correo de asignación enviado a " . $solicitud->responsable->email);
                } catch (\Exception $e) {
                    Log::error("NotifySolicitudAsignadaJob Error Email: " . $e->getMessage());
                }
            } else {
                Log::warning("NotifySolicitudAsignadaJob: El responsable interno no tiene correo configurado.");
            }

            // 1.2 Notificación en tiempo real (Portal Web y App Móvil si el técnico la tiene)
            try {
                $motherApiUrl = config('services.mother.api_url') ?? 'http://localhost:8000';
                $serviceToken = config('services.mother.service_token') ?? 'token_secreto_yamankutx_notificaciones';

                \Illuminate\Support\Facades\Http::withHeaders([
                    'X-SSO-Service-Token' => $serviceToken,
                    'Accept' => 'application/json'
                ])->timeout(2)->post("{$motherApiUrl}/api/sso/notifications/broadcast", [
                    'target_user_id' => $solicitud->responsable_id,
                    'title' => '¡Nuevo Ticket Asignado!',
                    'message' => "Se te ha asignado el ticket #{$solicitud->id}: '{$solicitud->titulo}'",
                    'app' => 'Tickets',
                    'ticket_id' => $solicitud->id,
                ]);
            } catch (\Exception $e) {
                Log::error("NotifySolicitudAsignadaJob Error Broadcast: " . $e->getMessage());
            }
        }
        elseif ($solicitud->responsable_tipo === 'externo') {
            // 2. Envío de SMS para Atención Externa
            $telefono = $solicitud->responsable->telefono;

            if ($telefono) {
                try {
                    $smsController = new SmsController();
                    $mensaje = "Estimado: {$solicitud->responsable->name}, se te asignado el ticket#: {$solicitud->id}, de la agencia: {$solicitud->agencia_id} revíselo.";

                    $apiSuccess = $smsController->sendSms($telefono, $mensaje);

                    if ($apiSuccess) {
                        Log::info("NotifySolicitudAsignadaJob: SMS de asignación enviado a " . $telefono);
                    } else {
                        Log::error("NotifySolicitudAsignadaJob: Falló el envío de SMS a " . $telefono);
                    }
                } catch (\Exception $e) {
                    Log::error("NotifySolicitudAsignadaJob Error SMS: " . $e->getMessage());
                }
            } else {
                Log::warning("NotifySolicitudAsignadaJob: El responsable externo no tiene teléfono configurado.");
            }
        }
    }
}
