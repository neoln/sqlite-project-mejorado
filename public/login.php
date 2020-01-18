<?php

// configuration
require("../includes/config.php");

if (!empty($_SESSION["user_id"])) {
    redirect("/");
}

$title = "Login";
$errors = [];
$user['user_name'] = '';

if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    if (empty($_POST['user_name'])) {
        $errors['user_name'] = 'Ingrese su nombre de usuario.';
    } else {
        $user['user_name'] = test_input( $_POST['user_name'] );
    }

    if (empty($_POST['password'])) {
        $errors['password'] = 'Ingrese su contraseña.';
    } else {
        $user['password'] = test_input( $_POST['password'] );
    }

    if ( count($errors) == 0 ) {
        
        if ( auth($user['user_name'], $user['password']) ) {
            redirect('/');
        } else {
            $errors['login_error'] = 'Nombre de usuario o contraseña no válidos o aún no ha activado su cuenta.';
        }
    }
    
}
    
// render header
require("../views/inc/header.html");
    
// render template
require("../views/user/login.html");
    
// render footer
require("../views/inc/footer.html");

/**
 * Funciones de persistencia
 */
function auth ($username, $password)
{
    $q = 'SELECT * FROM user
          WHERE
            user_name = ?
          AND
            activation = "activated";';

    $users = Db::query($q, $username);

    if (count($users) == 1){
        // first (and only) row
        $user = $users[0];

        if ( verifyPassword($password, $user['password']) ) {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            
            return true;
        }
    }

    return false;
}