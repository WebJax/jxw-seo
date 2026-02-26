<?php
/**
 * AI Engine Class
 *
 * Handles AI API integrations (OpenAI, Anthropic, Gemini)
 */

namespace LocalSEO;

class AI_Engine {

    /**
     * Parse a "retry in Xs" string from an API error message and return seconds as int.
     * Returns null if nothing found.
     */
    private static function parse_retry_seconds( $message ) {
        if ( preg_match( '/retry in (\d+(?:\.\d+)?)s/i', $message, $matches ) ) {
            return (int) ceil( (float) $matches[1] );
        }
        // OpenAI style: "Please try again in 20s" or "try again after 30 seconds"
        if ( preg_match( '/(?:try again (?:in|after))\s+(\d+(?:\.\d+)?)\s*s/i', $message, $matches ) ) {
            return (int) ceil( (float) $matches[1] );
        }
        return null;
    }

    /**
     * Build a user-friendly rate-limit WP_Error with optional countdown seconds.
     */
    private static function rate_limit_error( $raw_message ) {
        $retry = self::parse_retry_seconds( $raw_message );
        $friendly = __( 'AI-kvoten er midlertidigt opbrugt.', 'localseo-booster' );
        if ( $retry ) {
            $friendly .= ' ' . sprintf(
                /* translators: %d: seconds */ __( 'Prøv igen om %d sekunder.', 'localseo-booster' ),
                $retry
            );
        }
        return new \WP_Error(
            'rate_limit',
            $friendly,
            [ 'status' => 429, 'retry_seconds' => $retry ]
        );
    }
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
        } elseif ( $api_provider === 'gemini' ) {
            return self::call_gemini( $api_key, $prompt, $row_data );
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
            $msg  = $body['error']['message'] ?? 'Unknown error';
            $code = $body['error']['code'] ?? '';
            if ( $code === 'rate_limit_exceeded' || str_contains( $msg, 'Rate limit' ) || str_contains( $msg, 'quota' ) ) {
                return self::rate_limit_error( $msg );
            }
            return new \WP_Error( 'openai_error', $msg );
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
            $msg  = $body['error']['message'] ?? 'Unknown error';
            $type = $body['error']['type'] ?? '';
            if ( $type === 'rate_limit_error' || str_contains( $msg, 'rate limit' ) || str_contains( $msg, 'quota' ) ) {
                return self::rate_limit_error( $msg );
            }
            return new \WP_Error( 'anthropic_error', $msg );
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

    /**
     * Call Google Gemini API
     *
     * @param string $api_key
     * @param string $prompt
     * @param array $row_data
     * @return array|WP_Error
     */
    private static function call_gemini( $api_key, $prompt, $row_data ) {
        $city = $row_data['city'] ?? '';
        $service = $row_data['service_keyword'] ?? '';

        $response = wp_remote_post( 
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt . "\n\nGenerate content for: " . $service . " in " . $city . 
                                             "\n\nRespond with JSON: {\"intro\": \"...\", \"meta_title\": \"...\", \"meta_description\": \"...\"}"
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 500,
                    ]
                ]),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            $msg    = $body['error']['message'] ?? 'Unknown error';
            $status = $body['error']['code'] ?? 0;
            if ( $status === 429 || str_contains( $msg, 'Quota exceeded' ) || str_contains( $msg, 'quota' ) ) {
                return self::rate_limit_error( $msg );
            }
            return new \WP_Error( 'gemini_error', $msg );
        }

        $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
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
