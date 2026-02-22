    </div>

    <footer class="pie text-muted py-5 mt-5" style="font-size: 12px;
  background-color: #001042 !important;">
      <div class="container">
        <p class="float-end mb-1">
          <a href="#">Volver arriba</a>
        </p>
        <p class="mb-1 text-white">Seguimiento de App RutaEx-Latam</p>
        <p class="mb-0 text-white">Desarrollado por:<a href="#"> @albertoCalero</a></p>
      </div>
  </footer>


  <?php
      $paginaActual = isset($_GET['enlace']) ? explode("/", $_GET['enlace'])[0] : "inicio";

$scripts = [
    "global" => [
        '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>',
        '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>',
        '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>',
        '<script src="'.RUTA_URL.'vista/js/js/sweetalert2@11.js"></script>',
        '<script src="'.RUTA_URL.'vista/js/select2-init.js"></script>'
    
    ],
    "datatables" => [
        '<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>',
        '<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>',
        '<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>',
        '<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>',
        '<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>',
        '<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>',
        '<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>',
         '<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>'
    ],
    "maps" => [
        '<script>
            let map, marker;
            function initMap() {
                const initialPosition = { lat: 12.13282, lng: -86.2504 };
                map = new google.maps.Map(document.getElementById("map"), { center: initialPosition, zoom: 15 });
                marker = new google.maps.Marker({ position: initialPosition, map: map, draggable: true });

                marker.addListener("dragend", (event) => {
                    document.getElementById("latitud").value = event.latLng.lat();
                    document.getElementById("longitud").value = event.latLng.lng();
                });

                map.addListener("click", (event) => {
                    marker.setPosition(event.latLng);
                    document.getElementById("latitud").value = event.latLng.lat();
                    document.getElementById("longitud").value = event.latLng.lng();
                });
            }
        </script>'
    ]
];

echo implode("\n", $scripts["global"]);

if (!empty($usaDataTables)) {
    echo implode("\n", $scripts["datatables"]);
}

if ($paginaActual == "editar") {
    echo implode("\n", $scripts["maps"]);
}
?>

<?php
// Mostrar flash (SweetAlert) si existe
require_once __DIR__ . '/../../utils/session.php';
$flash = get_flash();
if ($flash) {
    $type = $flash['type'] === 'success' ? 'success' : 'error';
    $msg = addslashes($flash['message']);
    echo "<script>document.addEventListener('DOMContentLoaded', function(){ Swal.fire({icon: '$type', title: '', text: '$msg'}); });</script>";
}
?>
