<?php
/**
 * AI Engine Class
 *
 * Handles AI API integrations (OpenAI, Anthropic)
 */

namespace LocalSEO;

class AI_Engine {
    /**
     * Generate AI content for a row
     *
     * @param array $row_data Array with city, zip, service_keyword
     * @return array|WP_Error Array with intro, meta_title, meta_description or WP_Error on failure
     */
    public static function generate_content( $row_data ) {
        $api_provider = get_option( 'localseo_api_provider', 'openai' );
        $api_key = get_option( 'localseo_api_key', '' );

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'API key not configured', 'localseo-booster' ) );
        }

        // Get the system prompt template
        $system_prompt = get_option( 'localseo_system_prompt', 
            'You are an SEO expert for a local service company. Write a 50-word intro for {service} in {city} ({zip}). Focus on local expertise and trust.'
        );

        // Replace placeholders
        $prompt = str_replace(
            [ '{service}', '{city}', '{zip}' ],
            [ 
                $row_data['service_keyword'] ?? '', 
                $row_data['city'] ?? '', 
                $row_data['zip'] ?? '' 
            ],
            $system_prompt
        );

        if ( $api_provider === 'openai' ) {
            return self::call_openai( $api_key, $prompt, $row_data );
        } elseif ( $api_provider === 'anthropic' ) {
            return self::call_anthropic( $api_key, $prompt, $row_data );
        }

        return new \WP_Error( 'invalid_provider', __( 'Invalid API provider', 'localseo-booster' ) );
    }

    /**
     * Call OpenAI API
     *
     * @param string $api_key
     * @param string $prompt
     * @param array $row_data
     * @return array|WP_Error
     */
    private static function call_openai( $api_key, $prompt, $row_data ) {
        $city = $row_data['city'] ?? '';
        $service = $row_data['service_keyword'] ?? '';

        // Create structured prompt for JSON response
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an SEO content generator. Always respond with valid JSON containing: intro (50 words), meta_title (60 chars), meta_description (155 chars).'
            ],
            [
                'role' => 'user',
                'content' => $prompt . "\n\nGenerate content for: " . $service . " in " . $city . 
                           "\n\nRespond with JSON: {\"intro\": \"...\", \"meta_title\": \"...\", \"meta_description\": \"...\"}"
            ]
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'openai_error', $body['error']['message'] ?? 'Unknown error' );
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        
        // Try to parse JSON response
        $parsed = json_decode( $content, true );
        if ( $parsed && isset( $parsed['intro'] ) ) {
            return [
                'ai_generated_intro' => sanitize_textarea_field( $parsed['intro'] ),
                'meta_title' => sanitize_text_field( $parsed['meta_title'] ?? '' ),
                'meta_description' => sanitize_textarea_field( $parsed['meta_description'] ?? '' ),
            ];
        }

        // Fallback: use the content as intro
        return [
            'ai_generated_intro' => sanitize_textarea_field( $content ),
            'meta_title' => sanitize_text_field( $service . ' in ' . $city ),
            'meta_description' => sanitize_textarea_field( substr( $content, 0, 155 ) ),
        ];
    }

    /**
     * Call Anthropic API
     *
     * @param string $api_key
     * @param string $prompt
     * @param array $row_data
     * @return array|WP_Error
     */
    private static function call_anthropic( $api_key, $prompt, $row_data ) {
        $city = $row_data['city'] ?? '';
        $service = $row_data['service_keyword'] ?? '';

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 500,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt . "\n\nGenerate content for: " . $service . " in " . $city . 
                                   "\n\nRespond with JSON: {\"intro\": \"...\", \"meta_title\": \"...\", \"meta_description\": \"...\"}"
                    ]
                ],
            ]),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'anthropic_error', $body['error']['message'] ?? 'Unknown error' );
        }

        $content = $body['content'][0]['text'] ?? '';
        
        // Try to parse JSON response
        $parsed = json_decode( $content, true );
        if ( $parsed && isset( $parsed['intro'] ) ) {
            return [
                'ai_generated_intro' => sanitize_textarea_field( $parsed['intro'] ),
                'meta_title' => sanitize_text_field( $parsed['meta_title'] ?? '' ),
                'meta_description' => sanitize_textarea_field( $parsed['meta_description'] ?? '' ),
            ];
        }

        // Fallback: use the content as intro
        return [
            'ai_generated_intro' => sanitize_textarea_field( $content ),
            'meta_title' => sanitize_text_field( $service . ' in ' . $city ),
            'meta_description' => sanitize_textarea_field( substr( $content, 0, 155 ) ),
        ];
    }
}
