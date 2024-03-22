<?php

declare(strict_types=1);

namespace WebBook\Forms\Classes;

use RainLab\Translate\Classes\Translator;
use WebBook\Forms\Models\Settings;

trait ReCaptcha
{
    /**
     * @var RainLab\Translate\Classes\Translator translator object
     */
    protected $translator;

    /**
     * @var string the active locale code
     */
    public $activeLocale;

    public function init(): void
    {
        if (BackendHelpers::isTranslatePlugin()) {
            $this->translator = Translator::instance();
        }
    }

    private function isReCaptchaEnabled(): bool
    {
        return $this->property('recaptcha_enabled') && '' != Settings::get('recaptcha_site_key') && '' != Settings::get('recaptcha_secret_key');
    }

    private function isReCaptchaMisconfigured(): bool
    {
        return $this->property('recaptcha_enabled') && ('' == Settings::get('recaptcha_site_key') || '' == Settings::get('recaptcha_secret_key'));
    }

    private function loadReCaptcha(): void
    {
        $this->addJs('https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit', ['defer' => true]);
        $this->addJs('assets/js/recaptcha.js');
    }
}
