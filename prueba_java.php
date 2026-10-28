<?php
header("Content-Type: text/plain; charset=UTF-8");

if (getenv("DB_PASSWORD") !== false && getenv("DB_PASSWORD") !== "") {
    echo "DB_PASSWORD configurada en Apache";
} else {
    echo "DB_PASSWORD no configurada en Apache";
}
?>