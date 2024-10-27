<?php

class SDG_AI_Analyzer {
    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';
    
    public function __construct() {
        $this->api_key = get_option('sdg_claude_api_key');
    }

    public function generate_deck_content($metrics) {
        if (empty($this->api_key)) {
            throw new Exception('Claude API key not configured');
        }

        $prompt = $this->build_analysis_prompt($metrics);
        
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-opus-20240229',  // Using latest Claude model
                'max_tokens' => 1500,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ))
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $this->parse_ai_response($body);
    }

    private function build_analysis_prompt($metrics) {
        return "You are an expert business analyst creating content for an investment pitch deck. 
        Based on the following e-commerce metrics, create compelling content:

        Business Overview:
        - Company: {$metrics['basic_info']['company_name']}
        - Industry: {$metrics['basic_info']['industry']}
        - Revenue: {$metrics['financial_metrics']['total_revenue']}
        - Growth Rate: {$metrics['financial_metrics']['growth_rate']}%
        - Customer Base: {$metrics['customer_metrics']['total_customers']}
        
        Please provide:
        1. A compelling executive summary (2-3 paragraphs)
        2. Market opportunity analysis with specific industry insights
        3. Growth strategy recommendations based on the data
        4. Key investment highlights (bullet points)
        5. Future projections and potential (be specific but realistic)
        
        Format the response in clear sections with headers.";
    }

    private function parse_ai_response($response) {
        if (empty($response['content'])) {
            throw new Exception('Invalid Claude API response');
        }

        return $response['content'][0]['text'];
    }
}
