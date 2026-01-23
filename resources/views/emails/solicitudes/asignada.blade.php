<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud Asignada</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">

    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <!-- Main Container -->
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600; letter-spacing: -0.5px;">
                                Nueva Solicitud Asignada
                            </h1>
                            <p style="margin: 8px 0 0 0; color: #d1fae5; font-size: 14px;">
                                Sistema de Gestión de Solicitudes
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">

                            <!-- Greeting -->
                            <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.5;">
                                Estimado(a) <strong style="color: #111827;">{{ $solicitud->responsable->name ?? 'Responsable' }}</strong>,
                            </p>

                            <p style="margin: 0 0 30px 0; color: #6b7280; font-size: 15px; line-height: 1.6;">
                                Se le ha asignado una nueva solicitud que requiere su atención. A continuación encontrará los detalles:
                            </p>

                            <!-- Ticket Info Card -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f9fafb; border-radius: 6px; overflow: hidden; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 20px; border-left: 4px solid #10b981;">

                                        <!-- Ticket Number -->
                                        <div style="margin-bottom: 16px;">
                                            <span style="display: inline-block; background-color: #10b981; color: #ffffff; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px;">
                                                TICKET #{{ $solicitud->id }}
                                            </span>
                                        </div>

                                        <!-- Title -->
                                        <h2 style="margin: 0 0 20px 0; color: #111827; font-size: 18px; font-weight: 600; line-height: 1.4;">
                                            {{ $solicitud->titulo }}
                                        </h2>

                                        <!-- Details Grid -->
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 13px; width: 140px; vertical-align: top;">
                                                    <strong style="color: #374151;">Solicitante:</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #111827; font-size: 13px;">
                                                    {{ $solicitud->creadoPor->name ?? 'Usuario Desconocido' }}
                                                </td>
                                            </tr>
                                            @if($solicitud->creadoPor && ($solicitud->creadoPor->puesto || $solicitud->creadoPor->cargo))
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 13px; vertical-align: top;">
                                                    <strong style="color: #374151;">Cargo:</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #111827; font-size: 13px;">
                                                    {{ $solicitud->creadoPor->puesto->nombre ?? $solicitud->creadoPor->cargo ?? 'Sin Puesto' }}
                                                </td>
                                            </tr>
                                            @endif
                                            @if($solicitud->agencia)
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 13px; vertical-align: top;">
                                                    <strong style="color: #374151;">Agencia:</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #111827; font-size: 13px;">
                                                    {{ $solicitud->agencia->nombre ?? $solicitud->agencia_id }}
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 13px; vertical-align: top;">
                                                    <strong style="color: #374151;">Fecha de Creación:</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #111827; font-size: 13px;">
                                                    {{ $solicitud->created_at->format('d/m/Y H:i') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 13px; vertical-align: top;">
                                                    <strong style="color: #374151;">Estado:</strong>
                                                </td>
                                                <td style="padding: 8px 0;">
                                                    <span style="display: inline-block; background-color: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 500;">
                                                        {{ ucfirst(str_replace('_', ' ', $solicitud->estado)) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>

                            <!-- Description -->
                            <div style="margin-bottom: 30px;">
                                <h3 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Descripción
                                </h3>
                                <div style="background-color: #f9fafb; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb;">
                                    <p style="margin: 0; color: #4b5563; font-size: 14px; line-height: 1.6; white-space: pre-wrap;">{{ $solicitud->descripcion }}</p>
                                </div>
                            </div>

                            <!-- Call to Action -->
                            <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 20px; text-align: center;">
                                <p style="margin: 0 0 16px 0; color: #065f46; font-size: 14px; font-weight: 600;">
                                    📌 Acción Requerida
                                </p>
                                <p style="margin: 0 0 20px 0; color: #047857; font-size: 13px; line-height: 1.5;">
                                    Haga clic en el botón para revisar y dar seguimiento a esta solicitud.
                                </p>
                                <a href="{{ $urlSolicitud }}" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 12px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);">
                                    Ver Solicitud #{{ $solicitud->id }}
                                </a>
                            </div>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px 40px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 8px 0; color: #9ca3af; font-size: 12px; text-align: center; line-height: 1.5;">
                                Este es un mensaje automático del Sistema de Gestión de Solicitudes.
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; text-align: center; line-height: 1.5;">
                                Por favor, no responda a este correo electrónico.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
