<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ImageOptimizer
{
    /**
     * Optimiza y comprime una imagen a WebP localmente en un directorio temporal.
     * Retorna un arreglo con la ruta temporal y el nombre sugerido del archivo.
     * Si no es imagen, retorna la ruta temporal original del archivo y su nombre original.
     *
     * @param UploadedFile $file
     * @param int $maxDim Dimensión máxima de ancho o alto (e.g. 1920px)
     * @param int $quality Calidad de compresión WebP (1-100)
     * @return array ['path' => string, 'name' => string, 'is_optimized' => bool]
     */
    public static function optimizeForUpload(UploadedFile $file, int $maxDim = 1920, int $quality = 70): array
    {
        $originalName = $file->getClientOriginalName();
        $tempPath = $file->getRealPath();

        // Si la extensión GD no está instalada o habilitada, retornamos el archivo original
        if (!\extension_loaded('gd') || !\function_exists('imagecreatetruecolor')) {
            return ['path' => $tempPath, 'name' => $originalName, 'is_optimized' => false];
        }

        $extension = \strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        // Lista de extensiones y mimes de imagen que podemos optimizar con GD
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $isImage = \in_array($extension, $imageExtensions) && \str_starts_with($mime, 'image/');

        if (!$isImage) {
            return ['path' => $tempPath, 'name' => $originalName, 'is_optimized' => false];
        }

        // Cargar imagen según su formato original
        switch ($extension) {
            case 'jpeg':
            case 'jpg': $src = @\imagecreatefromjpeg($tempPath); break;
            case 'png': $src = @\imagecreatefrompng($tempPath); break;
            case 'webp': $src = @\imagecreatefromwebp($tempPath); break;
            case 'gif': $src = @\imagecreatefromgif($tempPath); break;
            default: $src = false;
        }

        if (!$src) {
            return ['path' => $tempPath, 'name' => $originalName, 'is_optimized' => false];
        }

        // Dimensiones originales
        $width = \imagesx($src);
        $height = \imagesy($src);

        // Calcular nuevas dimensiones preservando relación de aspecto
        if ($width > $maxDim || $height > $maxDim) {
            $ratio = $width / $height;
            if ($ratio > 1) {
                $newWidth = $maxDim;
                $newHeight = (int)\round($maxDim / $ratio);
            } else {
                $newHeight = $maxDim;
                $newWidth = (int)\round($maxDim * $ratio);
            }
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // Crear lienzo destino
        $dst = \imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG/WebP/GIF
        \imagealphablending($dst, false);
        \imagesavealpha($dst, true);

        // Redimensionar e interpolar
        \imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Generar nombre único terminado en .webp
        $sanitizedName = \preg_replace('/[^A-Za-z0-9_\-]/', '', \pathinfo($originalName, PATHINFO_FILENAME));
        $filename = $sanitizedName . '.webp';
        
        // Crear directorio temporal si no existe
        $tempDir = storage_path('app/temp_optimized');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }
        
        $optimizedPath = $tempDir . '/' . uniqid() . '_' . $filename;
        
        // Guardar como WebP optimizado
        \imagewebp($dst, $optimizedPath, $quality);

        // Liberar memoria
        \imagedestroy($src);
        \imagedestroy($dst);

        return ['path' => $optimizedPath, 'name' => $filename, 'is_optimized' => true];
    }
}
