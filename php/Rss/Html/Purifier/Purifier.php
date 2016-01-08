<?php
namespace Rpc\Html\Purifier;

class Purifier
{
    protected $purifier;

    public function __construct($options = array())
    {
        $config = \HTMLPurifier_Config::createDefault ();
        foreach ($options as $optkey => $optvalue) {
            $config->set ( $optkey, $optvalue );
        }
        $config->set ( 'Cache.DefinitionImpl', 'Serializer');
        $config->set ( 'Cache.SerializerPath', __DIR__ . DIRECTORY_SEPARATOR . 'cache' );
        //$config->set ( 'Output.TidyFormat', true );
        $this->purifier = new \HTMLPurifier ( $config );
    } // function __construct

    /**
     * Apply HTMLPurifier to an HTML string
     *
     * @param  string $content
     * @return string
     */
    public function purify($content)
    {
        $options [0] ['HTML.Allowed'] = '';

        return $this->purifier->purify ( $content );
    } // function purify
} // class Purifier
