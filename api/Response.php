<?php

class Response
{
    /**
     * Send JSON response and exit
     */
    public function __construct($success, $message, $data = null, $httpCode = 200)
    {
        http_response_code($httpCode);

        if ($success === true) {
            echo json_encode(true);
            exit;
        }

        if ($success === false) {
            echo json_encode(false);
            exit;
        }

        // If error, return error object
        $response = ['success' => $success, 'message' => $message];

        if ($data !== null) {
            $response = array_merge($response, $data);
        }

        echo json_encode($response);
        exit;
    }
}
