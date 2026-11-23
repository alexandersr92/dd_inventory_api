<?php

namespace Database\Seeders;

use App\Models\ProductRelease;
use Illuminate\Database\Seeder;

class ProductReleasesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $version = '1.1.0';
        $title = 'Más flexibilidad al facturar y nuevas opciones para tu negocio';

        $body = implode("\n\n", [
            'Ahora podés facturar productos o servicios no registrados mediante conceptos libres, indicando descripción, cantidad, costo y precio, sin crear productos ni afectar inventario. Activá esta opción en Configuración → Facturación.',
            'Elegí si el vendedor debe identificarse después de cada venta o mantener su sesión iniciada para continuar facturando. El cierre después de cada venta sigue siendo la opción predeterminada.',
            'Las proformas y cotizaciones ahora son opcionales. Podés activarlas en Configuración → Facturación; sus accesos aparecen únicamente cuando están habilitadas.',
            'Los borradores de ventas también son opcionales. Activá la función para guardar ventas pendientes y continuarlas posteriormente.',
            'Incorporamos el apartado Novedades, donde podrás consultar las nuevas versiones y los cambios de DipleBill.',
        ]);

        $createdCount = 0;
        $skippedCount = 0;

        $existing = ProductRelease::where('version', $version)->get();

        if ($existing->count() > 1) {
            $msg = "Conflicto detectado: Existen {$existing->count()} registros para la versión {$version}. Se conservaron todos sin modificaciones.";
            if ($this->command) {
                $this->command->warn($msg);
            }
            $skippedCount += $existing->count();
        } elseif ($existing->count() === 1) {
            $msg = "La versión {$version} ya existe en la base de datos central. Se conservó intacta (contenido, fecha y estado de publicación sin modificaciones).";
            if ($this->command) {
                $this->command->info($msg);
            }
            $skippedCount++;
        } else {
            ProductRelease::create([
                'version' => $version,
                'title' => $title,
                'body' => $body,
                'published_at' => now(),
            ]);
            $msg = "Publicación {$version} creada exitosamente con estado publicado en la base de datos central.";
            if ($this->command) {
                $this->command->info($msg);
            }
            $createdCount++;
        }

        $summary = "Resumen de carga de novedades: {$createdCount} creada(s), {$skippedCount} omitida(s).";
        if ($this->command) {
            $this->command->info($summary);
        }
    }
}
