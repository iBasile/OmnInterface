<?php
/**
 * Client pour l'API OmniRoute (compatible OpenAI : /v1/chat/completions, /v1/models).
 * OmniRoute expose par défaut un endpoint local, ex: http://localhost:20128/v1
 *
 * NOTE IMPORTANTE : si OmnInterface est hébergé sur AlwaysData (serveur distant)
 * et que le serveur OmniRoute tourne sur "localhost" de la MACHINE DE
 * L'UTILISATEUR, le serveur AlwaysData ne pourra pas l'atteindre directement
 * (ce n'est pas le même "localhost"). Dans ce cas, l'utilisateur doit soit :
 *   - exposer OmniRoute sur une adresse joignable depuis Internet (tunnel,
 *     reverse proxy, IP publique + port ouvert), soit
 *   - héberger OmnInterface lui-même en local, à côté d'OmniRoute.
 * Voir README.md pour plus de détails.
 */
class OmniRouteClient
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct(string $baseUrl, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /** Liste des modèles disponibles sur le routeur (GET /models). */
    public function listModels(): array
    {
        [$status, $body, $error] = $this->request('GET', '/models');

        if ($error !== null) {
            throw new RuntimeException($error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('OmniRoute a répondu avec le code ' . $status . '.');
        }

        $data = json_decode($body, true);
        $models = [];
        foreach ($data['data'] ?? [] as $model) {
            if (!empty($model['id'])) {
                $models[] = $model['id'];
            }
        }
        sort($models);
        return $models;
    }

    /**
     * Envoie une conversation complète et récupère la réponse de l'assistant.
     * @param array $messages [['role' => 'user'|'assistant'|'system', 'content' => string], ...]
     */
    public function chat(array $messages, ?string $model = null): string
    {
        $payload = [
            'model' => $model ?: 'auto',
            'messages' => $messages,
            'stream' => false,
        ];

        [$status, $body, $error] = $this->request('POST', '/chat/completions', $payload);

        if ($error !== null) {
            throw new RuntimeException('Impossible de joindre OmniRoute : ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            $decoded = json_decode($body, true);
            $message = $decoded['error']['message'] ?? $decoded['error'] ?? $body;
            throw new RuntimeException('OmniRoute (HTTP ' . $status . ') : ' . (is_string($message) ? $message : json_encode($message)));
        }

        $data = json_decode($body, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new RuntimeException('Réponse OmniRoute inattendue (pas de contenu).');
        }

        return $content;
    }

    /** @return array{0:int,1:string,2:?string} [statusCode, body, errorMessage] */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $ch = curl_init($this->baseUrl . $path);

        $headers = ['Accept: application/json'];
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => OMNIROUTE_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return [$status, (string) $body, $error];
    }
}
