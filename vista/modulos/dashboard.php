<?php 


//iniciar sesion

session_start();

if(!isset($_SESSION['registrado']))
{
    header('location:'.RUTA_FRONT.'login');
    die();
}
else
{

include("vista/includes/header.php");

?>


<div class="row">
    <div class="col-sm-6">
        <h3>Dashboard</h3>
    </div> 
</div>
<div class="row mt-2 caja">
    <h1>BIENVENIDO ADMIN: <?php echo $_SESSION['nombre']; ?> </h1>
</div>
<?php 

include("vista/includes/footer.php");

}

?>
