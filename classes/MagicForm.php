<?php

declare(strict_types=1);

namespace WebBook\Forms\Classes;

use Carbon\Carbon;
use Cms\Classes\ComponentBase;
use Event;
use Flash;
use Illuminate\Http\RedirectResponse;
use Redirect;
use WebBook\Forms\Models\Record;
use WebBook\Forms\Models\Settings;

abstract class MagicForm extends ComponentBase
{
    use ReCaptcha;
    use SharedProperties;

    public bool $recaptcha_enabled;
    public bool $recaptcha_misconfigured;
    public ?string $recaptcha_warn;

    public function onRun(): void
    {
        $this->recaptcha_enabled = $this->isReCaptchaEnabled();
        $this->recaptcha_misconfigured = $this->isReCaptchaMisconfigured();

        if ($this->isReCaptchaEnabled()) {
            $this->loadReCaptcha();
        }

        if ($this->isReCaptchaMisconfigured()) {
            $this->recaptcha_warn = __('Warning: reCAPTCHA is not properly configured. Please, goto Backend > Settings > CMS > Magic Forms and configure.');
        }
    }

    public function settings(): array
    {
        return [
            'recaptcha_site_key' => Settings::get('recaptcha_site_key'),
            'recaptcha_secret_key' => Settings::get('recaptcha_secret_key'),
        ];
    }

    public function onFormSubmit(): RedirectResponse|array
    {
        // FLASH PARTIAL
        $flash_partial = $this->property('messages_partial', '@flash.htm');

        // CSRF CHECK
        if (\Config::get('cms.enableCsrfProtection') && (\Session::token() != post('_token'))) {
            throw new \AjaxException(['#'.$this->alias.'_forms_flash' => $this->renderPartial($flash_partial, ['status' => 'error', 'type' => 'danger', 'content' => __('Form session expired! Please refresh the page.')])]);
        }

        // LOAD TRANSLATOR PLUGIN
        if (BackendHelpers::isTranslatePlugin()) {
            $translator = \RainLab\Translate\Classes\Translator::instance();
            $translator->loadLocaleFromSession();
            $locale = $translator->getLocale();
            \RainLab\Translate\Models\Message::setContext($locale);
        }

        // FILTER ALLOWED FIELDS
        $allow = $this->property('allowed_fields');
        if (is_array($allow) && ! empty($allow)) {
            foreach ($allow as $field) {
                $post[$field] = post($field);
            }
            if ($this->isReCaptchaEnabled()) {
                $post['g-recaptcha-response'] = post('g-recaptcha-response');
            }
        } else {
            $post = post();
        }

        // SANITIZE FORM DATA
        if ('htmlspecialchars' == $this->property('sanitize_data')) {
            $post = $this->array_map_recursive(function ($value) {
                return htmlspecialchars($value, ENT_QUOTES);
            }, $post);
        }

        // VALIDATION PARAMETERS
        $rules = (array) $this->property('rules');
        $msgs = (array) $this->property('rules_messages');
        $custom_attributes = (array) $this->property('custom_attributes');

        // TRANSLATE CUSTOM ERROR MESSAGES
        if (BackendHelpers::isTranslatePlugin()) {
            foreach ($msgs as $rule => $msg) {
                $msgs[$rule] = \RainLab\Translate\Models\Message::trans($msg);
            }
        }

        // ADD reCAPTCHA VALIDATION
        if ($this->isReCaptchaEnabled()) {
            $rules['g-recaptcha-response'] = 'required';
            $custom_attributes['g-recaptcha-response'] = 'reCAPTCHA';
        }

        // ADD files $post for validation
        if ($this->property('uploader_enable')) {
            $post['files'] = \Input::file('files');
        }

        // DO FORM VALIDATION,
        $validator = \Validator::make($post, $rules, $msgs, $custom_attributes);

        // VALIDATE ALL + CAPTCHA EXISTS
        if ($validator->fails()) {
            // GET DEFAULT ERROR MESSAGE
            $message = $this->property('messages_errors');

            // LOOK FOR TRANSLATION
            if (BackendHelpers::isTranslatePlugin()) {
                $message = \RainLab\Translate\Models\Message::trans($message);
            }

            // THROW ERRORS
            if ('inline' == $this->property('feedback_mode')) {
                throw new \ValidationException($validator);
            }
            throw new \AjaxException($this->exceptionResponse($validator, ['status' => 'error', 'type' => 'danger', 'title' => $message, 'list' => $validator->messages()->all(), 'jerrors' => json_encode($validator->messages()->messages()), 'jscript' => $this->property('js_on_error')]));
        }

        // REMOVE EXTRA FIELDS FROM STORED DATA
        unset($post['_token'], $post['g-recaptcha-response'], $post['_session_key'], $post['files']);

        // FIRE BEFORE SAVE EVENT
        \Event::fire('webbook.forms.beforeSaveRecord', [&$post, $this]);

        if (count($custom_attributes)) {
            $post = collect($post)->mapWithKeys(function ($val, $key) use ($custom_attributes) {
                return [array_get($custom_attributes, $key, $key) => $val];
            })->all();
        }

        $record = new Record();
        $record->ip = $this->getIP();

        // SAVE RECORD TO DATABASE
        if (! $this->property('skip_database')) {
            $record->form_data = $post;
            $record->group = $this->property('group');

            // Attach files
            $this->attachFiles($record);

            $record->save(null, post('_session_key'));
        }

        // SEND NOTIFICATION EMAIL
        if ($this->property('mail_enabled')) {
            SendMail::sendNotification($this->getProperties(), $post, $record, $record->files);
        }

        // SEND AUTORESPONSE EMAIL
        if ($this->property('mail_resp_enabled')) {
            SendMail::sendAutoResponse($this->getProperties(), $post, $record);
        }

        // FIRE AFTER SAVE EVENT
        \Event::fire('webbook.forms.afterSaveRecord', [&$post, $this, $record]);

        // CHECK FOR REDIRECT
        if ($this->property('redirect')) {
            return \Redirect::to($this->property('redirect'));
        }

        // GET DEFAULT SUCCESS MESSAGE
        $message = $this->property('messages_success');

        // LOOK FOR TRANSLATION
        if (BackendHelpers::isTranslatePlugin()) {
            $message = \RainLab\Translate\Models\Message::trans($message);
        }

        // DISPLAY SUCCESS MESSAGE
        return ['#'.$this->alias.'_forms_flash' => $this->renderPartial($flash_partial, [
            'status' => 'success',
            'type' => 'success',
            'content' => $message,
            'jscript' => $this->property('js_on_success'),
        ])];
    }

    private function exceptionResponse($validator, $params)
    {
        // FLASH PARTIAL
        $flash_partial = $this->property('messages_partial', '@flash.htm');

        // EXCEPTION RESPONSE
        return ['#'.$this->alias.'_forms_flash' => $this->renderPartial($flash_partial, $params)];
    }

    private function getIP()
    {
        if ('full' == $this->property('anonymize_ip')) {
            return '(Not stored)';
        }

        $ip = \Request::getClientIp();

        if ('partial' == $this->property('anonymize_ip')) {
            return BackendHelpers::anonymizeIPv4($ip);
        }

        return $ip;
    }

    private function array_map_recursive($callback, $array)
    {
        $func = function ($item) use (&$func, &$callback) {
            return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
        };

        return array_map($func, $array);
    }

    private function attachFiles(Record $record)
    {
        if (empty(files('files'))) {
            return;
        }
        foreach (\Input::file('files') as $file) {
            $record->files()->create(['data' => $file], post('_session_key'));
        }
    }

    public static function gdprClean()
    {
        $gdpr_enable = Settings::get('gdpr_enable', false);
        $gdpr_days = Settings::get('gdpr_days', false);

        if (! $gdpr_enable) {
            \Flash::error(__('GDPR options are disabled'));

            return;
        }

        if ($gdpr_enable && is_numeric($gdpr_days)) {
            $days = Carbon::now()->subDays($gdpr_days);

            return Record::whereDate('created_at', '<', $days)->forceDelete();
        }

        \Flash::error(__('Invalid GDPR days setting value'));
    }
}
