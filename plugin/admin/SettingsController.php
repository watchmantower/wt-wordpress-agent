<?php

namespace WTHB\admin;

use WTHB\models\Options;

/**
 * Settings page controller - determines which view to display.
 */
class SettingsController
{
    /**
     * Handle settings page request.
     *
     * @return void
     */
    public function handle(): void 
    {
        $opts = Options::get_all();
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : '';

        if (Options::is_connected($opts)) {
            $this->render('screen-connected', ['opts' => $opts]);
            return;
        }

        switch ($mode) {
            case 'existing':
                $this->render('screen-existing', ['opts' => $opts]);
                break;

            case 'create':
                $this->render('screen-create', ['opts' => $opts]);
                break;

            case 'connecting':
                $this->render('screen-connecting', ['opts' => $opts]);
                break;

            case 'disconnected':
                $this->render('screen-disconnected', ['opts' => $opts]);
                break;

            default:
                $this->render('screen-entry', ['opts' => $opts]);
                break;
        }
    }

    /**
     * Render a view file.
     *
     * @param string $view View filename without .php extension
     * @param array $data Data to extract for the view
     * @return void
     */
    private function render(string $view, array $data = []): void
    {
        $file = WTHB_PLUGIN_PATH . 'admin/views/' . $view . '.php';

        if (!file_exists($file)) {
            echo '<div class="notice notice-error"><p>';
            /* translators: %s: view file name */
            echo esc_html(sprintf(__('View file not found: %s', 'watchman-tower'), $view));
            echo '</p></div>';
            return;
        }

        extract($data, EXTR_SKIP);
        require $file;
    }
}
