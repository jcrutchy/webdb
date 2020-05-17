<?php

declare(strict_types=1); # applies on file-by-file basis only

namespace webdb\encrypt;

# https://stackoverflow.com/questions/16600708/how-do-you-encrypt-and-decrypt-a-php-string
# $key=random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES); # use base64_encode to store in file

#####################################################################################################

function webdb_encrypt(string $message,string $key): string
{
  if (defined("SODIUM_CRYPTO_SECRETBOX_KEYBYTES")==false)
  {
    \webdb\utils\error_message("please enable extension=sodium in php.ini");
  }
  $key=base64_decode($key);
  if (mb_strlen($key,"8bit")!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
  {
    \webdb\utils\error_message("webdb_encrypt: incorrect key size (must be 32 bytes)");
  }
  $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $cipher=base64_encode($nonce.sodium_crypto_secretbox($message,$nonce,$key));
  sodium_memzero($message);
  sodium_memzero($key);
  return $cipher;
}

#####################################################################################################

function webdb_decrypt(string $encrypted,string $key): string
{
  if (defined("SODIUM_CRYPTO_SECRETBOX_NONCEBYTES")==false)
  {
    \webdb\utils\error_message("please enable extension=sodium in php.ini");
  }
  $key=base64_decode($key);
  $decoded=base64_decode($encrypted);
  $nonce=mb_substr($decoded,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES,"8bit");
  $ciphertext=mb_substr($decoded,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES,null,"8bit");
  $plain=sodium_crypto_secretbox_open($ciphertext,$nonce,$key);
  if (is_string($plain)==false)
  {
    \webdb\utils\error_message("webdb_decrypt: invalid MAC");
  }
  sodium_memzero($ciphertext);
  sodium_memzero($key);
  return $plain;
}

#####################################################################################################
