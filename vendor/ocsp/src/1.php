<?php
$cert=file_get_contents('/fed.pem');
echo openssl_x509_parse($cert);