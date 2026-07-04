<?php
session_start();
session_unset();
session_destroy();
echo "Sesion reiniciada correctamente. Ya podes cerrar esta pestaña y volver a probar el login/registro.";