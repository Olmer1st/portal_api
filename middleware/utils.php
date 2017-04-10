<?php
/**
 * Created by PhpStorm.
 * User: olmer
 * Date: 10/04/17
 * Time: 18:59
 */

function simple_encrypt($text)
{
    global $config;
    return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $config->secret_key, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
}
function simple_decrypt($encrypted_text)
{
    global $config;
    return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $config->secret_key, base64_decode($encrypted_text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
}