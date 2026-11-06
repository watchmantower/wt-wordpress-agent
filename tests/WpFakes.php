<?php
// Minimal stand-ins for the WordPress classes the plugin uses.

class WP_Error
{
    private $message;

    public function __construct($code = '', $message = '')
    {
        $this->message = $message;
    }

    public function get_error_message()
    {
        return $this->message;
    }
}

class WP_REST_Response
{
    public $data;
    public $status;

    public function __construct($data = null, $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function get_data()
    {
        return $this->data;
    }
}

class WP_REST_Request
{
    private $method;
    private $route;
    private $headers = [];
    private $body = '';

    public function __construct($method = 'GET', $route = '')
    {
        $this->method = $method;
        $this->route = $route;
    }

    public function set_header($name, $value)
    {
        $this->headers[strtolower($name)] = $value;
    }

    public function get_header($name)
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function set_body($body)
    {
        $this->body = $body;
    }

    public function get_body()
    {
        return $this->body;
    }

    public function get_method()
    {
        return $this->method;
    }

    public function get_route()
    {
        return $this->route;
    }
}

/** Thrown by the wp_send_json_* stubs, the way WordPress dies after sending. */
class WthbJsonSent extends \Exception
{
    public $success;
    public $data;

    public function __construct(bool $success, $data)
    {
        parent::__construct($success ? 'json success' : 'json error');
        $this->success = $success;
        $this->data = $data;
    }
}
