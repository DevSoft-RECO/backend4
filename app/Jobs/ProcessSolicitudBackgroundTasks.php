<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Solicitud;
use App\Mail\NuevaSolicitudTecnologica;

class ProcessSolicitudBackgroundTasks implements ShouldQueue
{
    use Queueable;

    public $solicitud;
    public $tempFiles;

    /**
     * Create a new job instance.
     *
     * @param Solicitud $solicitud
     * @param array $tempFiles
     */
    public function __construct(Solicitud $solicitud, array $tempFiles = [])
    {
        $this->solicitud = $solicitud;
        $this->tempFiles = $tempFiles;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("EJECUTANDO JOB para Solicitud: " . $this->solicitud->id);

        $evidenciasPaths = [];

        // 1. Mover archivos a GCS
        if (!empty($this->tempFiles)) {
            foreach ($this->tempFiles as $tempFile) {
                $localPath = $tempFile['path'];
                $originalName = $tempFile['name'];

                try {
                    if (Storage::disk('local')->exists($localPath)) {
                        $fileContent = Storage::disk('local')->get($localPath);
                        $filename = uniqid() . '_' . $originalName;
                        $gcsPath = 'gestiones/solicitudes/inicial/' . $filename;

                        if (Storage::disk('gcs')->put($gcsPath, $fileContent)) {
                            Log::info("STORE DEBUG (JOB): Archivo subido exitosamente a: " . $gcsPath);
                            $evidenciasPaths[] = $gcsPath;
                        } else {
                            Log::error("STORE DEBUG (JOB): Error al subir a GCS el archivo " . $filename);
                        }

                        // Eliminar temporal local
                        Storage::disk('local')->delete($localPath);
                    }
                } catch (\Exception $e) {
                    Log::error("STORE ERROR GCS (JOB): " . $e->getMessage());
                }
            }

            // Actualizar la solicitud con las rutas de GCS
            if (count($evidenciasPaths) > 0) {
                $this->solicitud->update(['evidencias_inicial' => $evidenciasPaths]);
                Log::info("STORE DEBUG (JOB): Update completado. Paths: " . json_encode($evidenciasPaths));
            }
        }

        // 2. Enviar notificaciones
        $this->sendNotifications();
    }

    private function sendNotifications()
    {
        if ($this->solicitud->categoria_general_id == 1) { // 1 = Tecnología
            try {
                Log::info("Enviando correo instante (JOB) para solicitud: " . $this->solicitud->id);
                Mail::to('soporte@yamankutxrl.com')->send(new NuevaSolicitudTecnologica($this->solicitud));
            } catch (\Exception $e) {
                Log::error("Error enviando correo de nueva solicitud (JOB): " . $e->getMessage());
            }
        }
    }
}
