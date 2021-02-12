<?php

/**
 * Simple and secure contact form using Ajax, validations inputs, SMTP protocol and Google reCAPTCHA v3 in PHP.
 * 
 * @see      https://github.com/raspgot/AjaxForm-PHPMailer-reCAPTCHA
 * @package  PHPMailer | reCAPTCHA v3
 * @author   Gauthier Witkowski <contact@raspgot.fr>
 * @link     https://raspgot.fr
 * @version  1.0.4
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

# https://www.php.net/manual/fr/timezones.php
date_default_timezone_set('America/Los_Angeles');

require __DIR__ . '/vendor/PHPMailer/Exception.php';
require __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/SMTP.php';
require __DIR__ . '/vendor/recaptcha/autoload.php';

class Ajax_Form {
    
    # Constants to redefined
    # Check this for more configurations: https://blog.mailtrap.io/phpmailer
    const HOST        = 'smtp.gmail.com'; # SMTP server
    const USERNAME    = 'reejitx'; # SMTP username
    const PASSWORD    = 'reejitxx'; # SMTP password
    const SMTP_SECURE = PHPMailer::ENCRYPTION_STARTTLS;
    const SMTP_AUTH   = true;
    const PORT        = 587;
    const SECRET_KEY  = '6Lf8pFQaAAAAAH-4JCddEvm_BqjVo-KIGkMrhNev'; # GOOGLE secret key
    const SUBJECT     = 'New message !';
    public $handler   = [
        'success'       => '✔️ Your message has been sent !',
        'token-error'   => '❌ Error recaptcha token.',
        'enter_name'    => '❌ Please enter your name.',
        'enter_email'   => '❌ Please enter a valid email.',
        'enter_message' => '❌ Please enter your message.',
        'ajax_only'     => '❌ Asynchronous anonymous.',
        'body'          => '
            <h1>{{subject}}</h1>
            <p><strong>Date :</strong> {{date}}</p>
            <p><strong>Name :</strong> {{name}}</p>
            <p><strong>E-Mail :</strong> {{email}}</p>
            <p><strong>Message :</strong> {{message}}</p>
        ',
    ];

    /**
     * Ajax_Form constructor
     */
    public function __construct() {

        # Check if request is Ajax request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' !== $_SERVER['HTTP_X_REQUESTED_WITH']) {
            $this->statusHandler('ajax_only', 'error');
        }

        # Check if fields has been entered and valid
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name    = !empty($_POST['name']) ? filter_var($this->secure($_POST['name']), FILTER_SANITIZE_STRING) : $this->statusHandler('enter_name');
            $email   = !empty($_POST['email']) ? filter_var($this->secure($_POST['email']), FILTER_SANITIZE_EMAIL) : $this->statusHandler('enter_email');
            $message = !empty($_POST['message']) ? filter_var($this->secure($_POST['message']), FILTER_SANITIZE_STRING) : $this->statusHandler('enter_message');
            $token   = !empty($_POST['recaptcha-token']) ? filter_var($this->secure($_POST['recaptcha-token']), FILTER_SANITIZE_STRING) : $this->statusHandler('token-error');
            $date    = new DateTime();
        }

        # Prepare body
        $body = $this->getString('body');
        $body = $this->template( $body, [
            'subject' => self::SUBJECT,
            'date'    => $date->format('j/m/Y H:i:s'),
            'name'    => $name,
            'email'   => $email,
            'message' => $message,
        ] );

        # Verifying the user's response
        $recaptcha = new \ReCaptcha\ReCaptcha(self::SECRET_KEY);
        $resp = $recaptcha
            ->setExpectedHostname($_SERVER['SERVER_NAME'])
            ->verify($token, $_SERVER['REMOTE_ADDR']);
            
        if ($resp->isSuccess()) {

            # Instance of PHPMailer
            $mail = new PHPMailer(true);
            $mail->setLanguage('en', __DIR__ . '/vendor/PHPMailer/language/');

            try {
                # Server settings
                $mail->SMTPDebug  = SMTP::DEBUG_OFF;   # Enable verbose debug output
                $mail->isSMTP();                       # Set mailer to use SMTP
                $mail->Host       = self::HOST;        # Specify main and backup SMTP servers
                $mail->SMTPAuth   = self::SMTP_AUTH;   # Enable SMTP authentication
                $mail->Username   = self::USERNAME;    # SMTP username
                $mail->Password   = self::PASSWORD;    # SMTP password
                $mail->SMTPSecure = self::SMTP_SECURE; # Enable TLS encryption, `ssl` also accepted
                $mail->Port       = self::PORT;        # TCP port
            
                # Recipients
                $mail->setFrom(self::USERNAME, 'Raspgot');
                $mail->addAddress($email, $name);
                $mail->AddCC(self::USERNAME, 'Dev_copy');
                $mail->addReplyTo(self::USERNAME, 'Information');
            
                # Content
                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true);
                $mail->Subject = self::SUBJECT;
                $mail->Body    = $body;
                $mail->AltBody = strip_tags($body);;
            
                # Send email
                $mail->send();
                $this->statusHandler('success');

            } catch (Exception $e) {
                die (json_encode( $mail->ErrorInfo ));
            }
        } else {
            die (json_encode( $resp->getErrorCodes() ));
        }
    }

    /**
     * Template string values
     *
     * @param string $string
     * @param array $vars
     * @return string
     */
    public function template($string, $vars)
    {
        foreach ($vars as $name => $val) {
            $string = str_replace("{{{$name}}}", $val, $string);
        }
        return $string;
    }

    /**
     * Get string from $string variable
     *
     * @param string $string
     * @return string
     */
    public function getString($string)
    {
        return isset($this->handler[$string]) ? $this->handler[$string] : $string;
    }

    /**
     * Secure inputs fields
     *
     * @param string $post
     * @return string
     */
    public function secure($post)
    {
        $post = htmlspecialchars($post);
        $post = stripslashes($post);
        $post = trim($post);
        return $post;
    }

    /**
     * Error or success message
     *
     * @param string $message
     * @param string $status
     * @return json
     */
    public function statusHandler($message)
    {
        die (json_encode($this->getString($message)));
    }

}

# Instanciation 
new Ajax_Form();
