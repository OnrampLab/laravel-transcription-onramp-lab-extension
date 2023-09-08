<?php

namespace OnrampLab\TranscriptionOnrampLabExtension;

use Illuminate\Support\ServiceProvider;
use OnrampLab\Transcription\Facades\Transcription;
use OnrampLab\TranscriptionOnrampLabExtension\AudioTranscribers\OnrampLabWhisperAudioTranscriber;

class TranscriptionOnrampLabExtensionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Transcription::addTranscriber('onramp_lab_whisper', fn (array $config) => new OnrampLabWhisperAudioTranscriber($config));
    }
}
