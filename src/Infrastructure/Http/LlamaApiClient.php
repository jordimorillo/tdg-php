<?php

namespace Infrastructure\Http;

class LlamaApiClient
{
    public function sendRequest(array $data): ?array
    {
        $ch = curl_init('http://localhost:11434/api/generate'); // Cambia esta URL si tu endpoint es diferente
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "Error al hacer la solicitud HTTP: " . curl_error($ch) . "\n";
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }
}
