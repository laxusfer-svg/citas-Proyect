<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
	header("Allow: POST");
	http_response_code(405);
	exit("Método no permitido.");
}

if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
	http_response_code(403);
	exit("Solicitud no válida.");
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
	$cookieParams = session_get_cookie_params();
	setcookie(session_name(), "", time() - 42000, $cookieParams["path"], $cookieParams["domain"], $cookieParams["secure"], $cookieParams["httponly"]);
}

session_destroy();

header("Location: /");
exit;

?>