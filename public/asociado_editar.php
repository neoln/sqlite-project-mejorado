<?php

// display errors, warnings, and notices
ini_set("display_errors", true);
error_reporting(E_ALL);

require("../includes/helpers.php");
require("../src/app/Db.php");
require("../src/app/Flash.php");

// enable sessions
session_start();

$pages = [
    '/login.php',
    '/logout.php',
    '/register.php'
];

// PHP_SELF: /asociado_editar.php
if ( !in_array($_SERVER["PHP_SELF"], $pages) ) {
    
    if (empty($_SESSION["user_id"])) {
        redirect("login.php");
    }
}

$errors = [];
$title = 'Editar asociado';
$message = '';

/**
 * chequear si existe el ID y es un número entero positivo 
 */
if ( $_SERVER["REQUEST_METHOD"] == "GET" )
{
    if (!array_key_exists('id_asociado', $_GET)) {
        $message = 'ID de parámetro URL no encontrado.';
        require("../views/error/error.html");
        exit;
    }
    
    if (!isPositiveInt($_GET['id_asociado'])) {
        $message = 'Identificador de usuario inválido.';
        require("../views/error/error.html");
        exit;
    }
    
    $asoc = obtenerAsociadoPorId($_GET['id_asociado']);

    if (!$asoc) {
        $message = 'No pudimos procesar su solicitud.';
        require("../views/error/error.html");
        exit;
    }

    $asoc['fech_nacimiento'] = dateToView($asoc['fech_nacimiento']);

    $_SESSION['id_asociado'] = $asoc['id_asociado'];
}

if ( $_SERVER["REQUEST_METHOD"] == "POST" ){

    $onlyLetters = '/^[a-zA-ZáéíóúÁÉÍÓÚÑñÜü ]+$/';
    $validEmail = '/^[\w.-]+@[\w.-]+\.[A-Za-z]{2,6}$/';

    if (empty($_POST['apellido'])) {
        $errors['apellido'] = 'Ingrese el apellido.';
    } else {
        $asoc['apellido'] = test_input( $_POST['apellido'] );

        if(!checkFormat($onlyLetters, $asoc['apellido'])) {
	        $errors['apellido'] = 'Solo se permiten letras (a-zA-Z), y espacios en blanco.';
	    }
    }

    if (empty($_POST['nombre'])) {
        $errors['nombre'] = 'Ingrese el nombre.';
    } else {
        $asoc['nombre'] = test_input( $_POST['nombre'] );

        if(!checkFormat($onlyLetters, $asoc['nombre'])) {
	        $errors['nombre'] = 'Solo se permiten letras (a-zA-Z), y espacios en blanco.';
	    }
    }

    if (empty($_POST['categoria'])) {
        $errors['categoria'] = "Seleccione una opción.";
    } else {
        $asoc['categoria'] = test_input($_POST["categoria"]);
    }

    if (empty($_POST['email'])) {
        $errors['email'] = 'Ingrese el correo electrónico.';
    } else {
        $asoc['email'] = test_input( $_POST['email'] );

        if ( !checkFormat($validEmail, $asoc['email'], true) ) {
            $errors['email'] = '\''. $asoc['email'] . '\' no es una dirección de correo electrónico válida.';
        }
    }

    if (!empty($_POST['nro_tel_linea'])) {
        $asoc['nro_tel_linea'] = test_input($_POST["nro_tel_linea"]);

        if ( !validar_tel($asoc['nro_tel_linea']) ) {
            $errors['nro_tel_linea'] = "El formato o el número de teléfono ingresado no es válido.";
        }
    } else {
        $asoc['nro_tel_linea'] = '';
    }

    if (empty($_POST['nro_tel_movil'])) {
        $errors['nro_tel_movil'] = "Ingrese el número móvil.";
    } else {
        $asoc['nro_tel_movil'] = test_input($_POST["nro_tel_movil"]);

        if ( !validar_tel($asoc['nro_tel_movil']) ) {
            $errors['nro_tel_movil'] = "El formato o el número de teléfono ingresado no es válido.";
        }
    }

    if (empty($_POST['domicilio'])) {
        $errors['domicilio'] = "Ingrese el domicilio.";
    } else {
        $asoc['domicilio'] = test_input($_POST["domicilio"]);
    }

    if (empty($_POST['provincia'])) {
        $errors['provincia'] = "Seleccione una opción.";
    } else {
        $asoc['provincia'] = test_input($_POST["provincia"]);
    }

    if (empty($_POST['localidad'])) {
        $errors['localidad'] = "Seleccione una opción.";
    } else {
        $asoc['localidad'] = test_input($_POST["localidad"]);
    }

    if ( count($errors) == 0 ) {

        $asoc['id_asociado'] = $_SESSION['id_asociado'];

        if ( actualizarAsociado( $asoc ) ) {
            Flash::addFlash('La acción se realizó correctamente.');
            redirect('/');
        }
    }
}

// render header
require("../views/inc/header.html");
    
// render template
require("../views/asociado/editar_asociado.html");
    
// render footer
require("../views/inc/footer.html");

/**
 * Funciones de persistencia
 */
function obtenerAsociadoPorId($id_asociado)
{
    $q = 'SELECT 
            a.id_asociado,
            a.nro_cuil,
            a.apellido,
            a.nombre,
            a.genero,
            a.tipo_documento,
            a.nro_documento,
            a.categoria,
            a.fech_nacimiento,
            e.email,
            a.domicilio,
            l.nombre AS localidad,
            p.nombre AS provincia 
          FROM
            asociado a 
            JOIN localidad l 
              ON a.id_localidad = l.id_localidad 
            JOIN provincia p 
              ON l.id_provincia = p.id_provincia 
            JOIN email e 
              ON a.id_asociado = e.id_asociado
          WHERE a.id_asociado = ? ;';

    $result = Db::query($q, $id_asociado);

    if(count($result) != 1) return false;
    
    $asociado = $result[0];

    $tel_movil = obtenerTelAsociado($id_asociado);

    $asociado['nro_tel_movil'] = $tel_movil['nro_tel'] ?? '';

    $tel_linea = obtenerTelAsociado($id_asociado, 'linea');

    $asociado['nro_tel_linea'] = $tel_linea['nro_tel'] ?? '';

    return $asociado;
}

function obtenerTelAsociado($id_asociado, $tipo = 'movil')
{
    $q = 'SELECT
            nro_tel
          FROM
            telefono
          WHERE  tipo = ?
          AND id_asociado = ? ;';

    $stmt = Db::getInstance()->prepare($q);

    if($stmt === false) return false;

    $result = $stmt->execute([$tipo, $id_asociado]);

    if (!$result) return false;

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function actualizarAsociado($asociado)
{
    $q = 'UPDATE 
            asociado 
          SET
            apellido = ?,
            nombre = ?,
            categoria = ?,
            domicilio = ?,
            id_localidad = ?,
            last_modified_on = ?
          WHERE id_asociado = ? ;';

    $last_modified_on = date('Y-m-d H:i:s');

    $result = Db::query(
        $q,
        $asociado['apellido'], $asociado['nombre'], $asociado['categoria'], $asociado['domicilio'], $asociado['localidad'], $last_modified_on, $asociado['id_asociado']
    );

    if(!$result) return false;

    $q = 'UPDATE
            email
          SET
            email = ?
          WHERE id_asociado = ? ;';
    
    $result = Db::query($q, $asociado['email'], $asociado['id_asociado']);

    if(!$result) return false;

    if ( !updateTelAsociado($asociado['id_asociado'], $asociado['nro_tel_movil']) ) {
        return false;
    }

    if ( !obtenerTelAsociado($asociado['id_asociado'], 'linea') ) {
        if ( !insertarTelAsociado($asociado['id_asociado'], $asociado['nro_tel_linea'], 'linea') ) {
            return false;
        }
    } else {
        if ( !updateTelAsociado($asociado['id_asociado'], $asociado['nro_tel_linea'], 'linea') ) {
            return false;
        }
    }

    return true;
}

function updateTelAsociado($id_asociado, $nro_tel, $tipo = 'movil')
{
    $q = 'UPDATE 
            telefono 
          SET
            nro_tel = ? 
          WHERE id_asociado = ? 
            AND tipo = ? ;';
          
    return Db::query($q, $nro_tel, $id_asociado, $tipo);
}

// método definido en asociado_registrar
function insertarTelAsociado($id_asociado, $nro_tel, $tipo = 'movil')
{
    $q = 'INSERT INTO telefono (nro_tel, tipo, id_asociado) VALUES (?, ?, ?); ';

    return Db::query($q, $nro_tel, $tipo, $id_asociado);
}

// método duplicado
function getAllProvinces()
{
    $q = 'SELECT * FROM provincia ;';
    return Db::query($q);
}