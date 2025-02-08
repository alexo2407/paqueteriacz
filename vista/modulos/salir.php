<?php 

session_start();
session_destroy();
header("location:".RUTA_FRONT);
exit();

