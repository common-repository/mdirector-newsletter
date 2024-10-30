<?php
namespace MDirectorNewsletter\includes;

use WP_Widget;

if (!class_exists('mdirectorWidget')) {

    /**
     * Class mdirectorWidget
     */
    class mdirectorWidget extends WP_Widget {
        public function __construct() {
            $widgetOps = array(
                'classname' => 'MDirectorNewsletter\includes\mdirectorWidget',
                'description' => __('WIDGET__TITLE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
            );

            parent::__construct('mdirectorWidget', 'MDirector Widget', $widgetOps);
        }

        // widget form creation
        public function form($instance) {
            $instance = wp_parse_args((array)$instance, array('title' => ''));
            $title = $instance['title'];
            $description = isset($instance['description']) ? $instance['description'] : '';

            echo '
            <p><label
                for="' . $this->get_field_id('title') . '">' .
                __('WIDGET-FORM__TITLE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                <input class="widefat"
                    id="' . $this->get_field_id('title') . '"
                    name="' . $this->get_field_name('title') . '"
                    type="text"
                    value="' . esc_attr($title) . '"/></label>
            </p>
            <p>
                <label for="' . $this->get_field_id('description') . '">' .
                    __('WIDGET-FORM__TEXT_SUPPORT',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                    <textarea class="widefat"
                        id="' . $this->get_field_id('description') . '"
                        name="' . $this->get_field_name('description') . '">' .
                            esc_attr($description) . '
                    </textarea>
                </label>
            </p>';
        }

        /**
         * @param array $newInstance
         * @param array $oldInstance
         *
         * @return array
         */
        public function update($newInstance, $oldInstance) {
            $instance = $oldInstance;
            $instance['title'] = $newInstance['title'];
            $instance['description'] = $newInstance['description'];

            return $instance;
        }

        /**
         * @param array $args
         * @param array $instance
         *
         * @throws \Throwable
         * @throws \Twig_Error_Loader
         * @throws \Twig_Error_Runtime
         * @throws \Twig_Error_Syntax
         */
        public function widget($args, $instance) {
            $MdirectorUtils = new Mdirector_Newsletter_Utils();
            $output = $MdirectorUtils->getRegisterFormHTML($args, $instance);

            echo $output;
            return;
        }
    }

    // register widget
    add_action('widgets_init', function () {
        return register_widget("MDirectorNewsletter\includes\mdirectorWidget");
    });
}
