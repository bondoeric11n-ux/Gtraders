<?php
$plain = 'S0perAdm!2025#';
$hash  = 'paste_hash_from_db_here';
var_dump(password_verify($plain, $hash));
