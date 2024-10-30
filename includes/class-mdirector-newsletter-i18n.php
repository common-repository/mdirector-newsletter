<?php

namespace MDirectorNewsletter\includes;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 * @author     MDirector
 */
class Mdirector_Newsletter_i18n
{

    /**
     * The domain specified for this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $domain The domain identifier for this plugin.
     */
    private $domain;

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function loadPluginTextDomain()
    {
        $moFile = MDIRECTOR_NEWSLETTER_PLUGIN_DIR
            . 'languages/' . $this->domain . '-' . get_locale() . '.mo';
        load_textdomain($this->domain, $moFile);
    }

    /**
     * Set the domain equal to that of the specified domain.
     *
     * @since    1.0.0
     *
     * @param    string $domain The domain that represents the locale of this plugin.
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }
}
