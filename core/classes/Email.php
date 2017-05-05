<?php

/**
 * Description of Email
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class Email {

    /**
     *
     * @var string
     */
    protected $body;

    /**
     *
     * @var string
     */
    protected $plain;

    /**
     *
     * @var mixed
     */
    protected $to;

    /**
     *
     * @var mixed
     */
    protected $from;

    /**
     *
     * @var string
     */
    protected $subject;

    /**
     *
     * @var array
     */
    protected $headers = [];

    /**
     *
     * @var array
     */
    protected $more = [];

    /**
     * @param string $template Name of text/html template file to send
     * @param array $variables Array of variables to fill into the template file
     * @param string $plain Name of text/plain template file to send with the html
     */
    public function __construct($template, $variables, $plain = null) {
        $this->body = template($template, $variables);
        if ($plain) $this->plain = template($plain, $variables, false);
    }

    /**
     * The recipient(s) of the email
     * @param mixed $to
     * @return $this
     */
    public function to($to) {
        $this->to = $to;
        return $this;
    }

    /**
     * The sender's email address
     * @param mixed $from
     * @return $this
     */
    public function from($from) {
        $this->from = $from;
        return $this;
    }

    /**
     * The subject of the email
     * @param string $subject
     * @return $this
     */
    public function subject($subject) {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set headers for the email
     * @param array $headers
     * @return $this
     */
    public function headers(array $headers) {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Add additional info for the mailer
     * @param array $more
     * @return $this
     */
    public function more(array $more) {
        $this->more = $more;
        return $this;
    }

    /**
     * Sends the email
     * @param mixed $to
     * @param string $subject
     * @param mixed $from
     */
    public function send($to = null, $subject = null, $from = null) {
        if ($to) $this->to($to);
        if ($subject) $this->subject($subject);
        if ($from) $this->from($from);
        $mailer = config('app.mailer');
        return call_user_func_array($mailer, [$this->to, $this->subject, $this->body, [
                'plain' => $this->plain,
                'headers' => $this->headers,
                'more' => $this->more
        ]]);
    }

}
