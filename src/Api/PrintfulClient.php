<?php

namespace PrintfulForFluentCart\Api;

defined('ABSPATH') || exit;

/**
 * Thin wrapper around the Printful REST API v2.
 *
 * All public methods return a plain array on success or WP_Error on failure.
 */
class PrintfulClient
{
    const API_BASE = 'https://api.printful.com';
    const TIMEOUT  = 30;

    /** @var string */
    private $apiKey;

    /**
     * @param string $apiKey  Leave empty to read from plugin settings.
     */
    public function __construct($apiKey = '')
    {
        if ($apiKey === '') {
            $settings = get_option('pifc_settings', []);
            $apiKey   = $settings['api_key'] ?? '';
        }
        $this->apiKey = $apiKey;
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    /** @return array|\WP_Error */
    public function getStore()
    {
        return $this->get('/store');
    }

    // ─── Sync Products ────────────────────────────────────────────────────────

    /**
     * @param  int    $offset
     * @param  int    $limit
     * @return array|\WP_Error
     */
    public function getProducts($offset = 0, $limit = 100)
    {
        return $this->get('/store/products', compact('offset', 'limit'));
    }

    /**
     * @param  int    $productId  Printful sync product ID.
     * @return array|\WP_Error
     */
    public function getProduct($productId)
    {
        return $this->get('/store/products/' . (int) $productId);
    }

    // ─── Catalog ─────────────────────────────────────────────────────────────

    /**
     * @param  int    $catalogProductId
     * @return array|\WP_Error
     */
    public function getCatalogProduct($catalogProductId)
    {
        return $this->get('/catalog/products/' . (int) $catalogProductId);
    }

    /**
     * @param  int    $catalogVariantId
     * @return array|\WP_Error
     */
    public function getCatalogVariant($catalogVariantId)
    {
        return $this->get('/catalog/variants/' . (int) $catalogVariantId);
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    /**
     * @param  array  $orderData
     * @return array|\WP_Error
     */
    public function createOrder(array $orderData)
    {
        return $this->post('/orders', $orderData);
    }

    /**
     * @param  int    $orderId  Printful order ID.
     * @return array|\WP_Error
     */
    public function getOrder($orderId)
    {
        return $this->get('/orders/' . (int) $orderId);
    }

    /**
     * Confirm a draft order (this triggers production and billing).
     *
     * @param  int    $orderId
     * @return array|\WP_Error
     */
    public function confirmOrder($orderId)
    {
        return $this->post('/orders/' . (int) $orderId . '/confirm', []);
    }

    /**
     * @param  int    $orderId
     * @param  array  $orderData
     * @return array|\WP_Error
     */
    public function updateOrder($orderId, array $orderData)
    {
        return $this->put('/orders/' . (int) $orderId, $orderData);
    }

    /**
     * Cancel / delete a Printful order (only possible while in draft/pending).
     *
     * @param  int    $orderId
     * @return array|\WP_Error
     */
    public function cancelOrder($orderId)
    {
        return $this->delete('/orders/' . (int) $orderId);
    }

    // ─── Shipping ─────────────────────────────────────────────────────────────

    /**
     * @param  array  $payload  { recipient, items, currency, locale }
     * @return array|\WP_Error  Array of rate objects.
     */
    public function getShippingRates(array $payload)
    {
        return $this->post('/shipping/rates', $payload);
    }

    // ─── Webhooks ─────────────────────────────────────────────────────────────

    /** @return array|\WP_Error */
    public function getWebhooks()
    {
        return $this->get('/webhooks');
    }

    /**
     * @param  string   $url
     * @param  string[] $types
     * @return array|\WP_Error
     */
    public function setWebhooks($url, array $types)
    {
        return $this->post('/webhooks', [
            'url'   => $url,
            'types' => $types,
        ]);
    }

    /** @return array|\WP_Error */
    public function deleteWebhooks()
    {
        return $this->delete('/webhooks');
    }

    // ─── Countries ────────────────────────────────────────────────────────────

    /** @return array|\WP_Error */
    public function getCountries()
    {
        return $this->get('/countries');
    }

    // ─── HTTP layer ───────────────────────────────────────────────────────────

    /**
     * Public pass-through for arbitrary GET endpoints (e.g. catalog browsing).
     *
     * @param  string $endpoint
     * @param  array  $params    Query-string params.
     * @return array|\WP_Error
     */
    public function get($endpoint, array $params = [])
    {
        $url = self::API_BASE . $endpoint;

        if (!empty($params)) {
            $url = add_query_arg(array_map('strval', $params), $url);
        }

        $response = wp_remote_get($url, $this->buildArgs());
        return $this->parseResponse($response, $endpoint);
    }

    /**
     * @param  string $endpoint
     * @param  array  $body
     * @return array|\WP_Error
     */
    private function post($endpoint, array $body)
    {
        $args = array_merge($this->buildArgs(), [
            'body' => wp_json_encode($body),
        ]);

        $response = wp_remote_post(self::API_BASE . $endpoint, $args);
        return $this->parseResponse($response, $endpoint);
    }

    /**
     * @param  string $endpoint
     * @param  array  $body
     * @return array|\WP_Error
     */
    private function put($endpoint, array $body)
    {
        $args = array_merge($this->buildArgs(), [
            'method' => 'PUT',
            'body'   => wp_json_encode($body),
        ]);

        $response = wp_remote_request(self::API_BASE . $endpoint, $args);
        return $this->parseResponse($response, $endpoint);
    }

    /**
     * @param  string $endpoint
     * @return array|\WP_Error
     */
    private function delete($endpoint)
    {
        $args = array_merge($this->buildArgs(), ['method' => 'DELETE']);

        $response = wp_remote_request(self::API_BASE . $endpoint, $args);
        return $this->parseResponse($response, $endpoint);
    }

    /** @return array */
    private function buildArgs()
    {
        return [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];
    }

    /**
     * @param  array|\WP_Error $response
     * @param  string          $endpoint  For error context.
     * @return array|\WP_Error
     */
    private function parseResponse($response, $endpoint = '')
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $message = '';

            if (isset($body['error']['message'])) {
                $message = $body['error']['message'];
            } elseif (isset($body['message'])) {
                $message = $body['message'];
            } else {
                $message = sprintf(
                    /* translators: 1: endpoint 2: HTTP code */
                    __('Printful API error on %1$s (HTTP %2$d).', 'printful-for-fluentcart'),
                    $endpoint,
                    $code
                );
            }

            return new \WP_Error('printful_api_error', $message, [
                'status'   => $code,
                'endpoint' => $endpoint,
                'body'     => $body,
            ]);
        }

        // Printful wraps successful payloads in a "result" key.
        if (isset($body['result'])) {
            return $body['result'];
        }

        return is_array($body) ? $body : [];
    }
}
