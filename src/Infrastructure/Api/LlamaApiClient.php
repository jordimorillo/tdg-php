<?php

namespace Infrastructure\Api;

class LlamaApiClient
{
    public function call(array $data)
    {
        $ch = curl_init('http://localhost:11434/api/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("Error making HTTP request: " . curl_error($ch));
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }
}
