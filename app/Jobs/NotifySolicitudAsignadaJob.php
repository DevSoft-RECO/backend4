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
