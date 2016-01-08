<?php

namespace Rss\Text\Unicode;

class Unicode
{
    /**
     * The filename that stores the unicode block definitions
     *
     * If this value starts with a slash (/) or a dot (.) the value of
     * $this->_data_dir will be ignored
     *
     * @var string
     * @access private
     */
    //private $_unicode_db_filename = 'unicode_blocks.dat';
    private $_unicode_db_filename = 'lang.dat';

    // since the unicode definitions are always going to be the same,
    // might as well share the memory for the db with all other instances
    // of this class
    public static $unicode_blocks = null;

    public function __construct()
    {
        if (! self::$unicode_blocks) {
            self::$unicode_blocks = unserialize(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $this->_unicode_db_filename));
        }
        var_dump(self::$unicode_blocks);
    } // function __construct()

} // class Unicode
