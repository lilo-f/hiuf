<?php
/**
 * Sanitiza a entrada de dados para prevenir XSS e outros ataques.
 *
 * @param string $data A string a ser sanitizada.
 * @return string A string sanitizada.
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Valida um endereço de e-mail.
 *
 * @param string $email O e-mail a ser validado.
 * @return bool Verdadeiro se o e-mail for válido, falso caso contrário.
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Valida um número de CPF.
 *
 * @param string $cpf O CPF a ser validado.
 * @return bool Verdadeiro se o CPF tiver 11 dígitos, falso caso contrário.
 */
function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return strlen($cpf) == 11;
}

// Em um projeto real, aqui você poderia adicionar mais funções de utilidade.