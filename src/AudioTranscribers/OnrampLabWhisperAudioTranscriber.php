<?php

namespace OnrampLab\TranscriptionOnrampLabExtension\AudioTranscribers;

use Aws\Credentials\Credentials;
use Aws\Lambda\LambdaClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OnrampLab\Transcription\Contracts\AudioTranscriber;
use OnrampLab\Transcription\Contracts\Callbackable;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\Models\TranscriptSegment;
use OnrampLab\Transcription\ValueObjects\Transcription;

class OnrampLabWhisperAudioTranscriber implements AudioTranscriber, Callbackable
{
    protected LambdaClient $client;

    protected string $callbackMethod = 'POST';

    protected string $callbackUrl = '';

    private const RESULT_STATUS_MAPPING = [
        'completed' => TranscriptionStatusEnum::COMPLETED,
        'failed' => TranscriptionStatusEnum::FAILED,
    ];

    public function __construct(array $config)
    {
        $this->client = new LambdaClient([
            'version' => '2015-03-31',
            'region' => $config['region'],
            'credentials' => new Credentials($config['access_key'], $config['access_secret']),
        ]);
    }

    /**
     * Transcribe audio file into text records in specific language.
     */
    public function transcribe(string $audioUrl, string $languageCode, ?int $maxSpeakerCount = null): Transcription
    {
        $id = Str::uuid()->toString();
        $languageCode = explode('-', $languageCode)[0];
        $payload = [
            'name' => $id,
            'audio_url' => $audioUrl,
            'language_code' => $languageCode,
            'callback_method' => $this->callbackMethod,
            'callback_url' => $this->callbackUrl,
        ];

        if (! is_null($maxSpeakerCount)) {
            $payload = array_merge(
                $payload,
                [
                    'enable_speaker_identification' => true,
                    'max_speaker_count' => $maxSpeakerCount,
                ],
            );
        }

        $this->client->invoke([
            'FunctionName' => 'open-ai-whisper-transcribe:v2',
            'InvocationType' => 'Event',
            'Payload' => json_encode($payload),
        ]);

        return new Transcription([
            'id' => $id,
            'status' => TranscriptionStatusEnum::PROCESSING,
        ]);
    }

    /**
     * Parse transcripts result of transcription and persist them into database.
     */
    public function parse(Transcription $transcription, Transcript $transcript): void
    {
        Collection::make(data_get($transcription->result, 'segments'))
            ->each(function (array $segment) use ($transcript) {
                $this->parseSegment($segment, $transcript);
            });
    }

    private function parseSegment(array $segment, Transcript $transcript): void
    {
        $words = Collection::make($segment['words'])
            ->map(fn (array $word) => [
                'start_time' => Carbon::parse($word['start'])->format('H:i:s.v'),
                'end_time' => Carbon::parse($word['end'])->format('H:i:s.v'),
                'content' => (string) Str::of($word['word'])->trim(),
            ])
            ->toArray();

        TranscriptSegment::create([
            'transcript_id' => $transcript->id,
            'start_time' => Carbon::parse($segment['start'])->format('H:i:s.v'),
            'end_time' => Carbon::parse($segment['end'])->format('H:i:s.v'),
            'content' => (string) Str::of($segment['text'])->trim(),
            'words' => $words,
            'speaker_label' => data_get($segment, 'speaker_label'),
        ]);
    }

    /**
     * Validate callback request from third-party service.
     */
    public function validate(array $requestHeader, array $requestBody): void
    {
        $rules = [
            'name' => 'required|string',
            'status' => 'required|string',
            'transcript' => 'nullable|array',
            'error_message' => 'nullable|string',
            'error_type' => 'nullable|string',
        ];
        $validator = Validator::make($requestBody, $rules);
        $validator->validate();
    }

    /**
     * Process callback request from third-party service.
     */
    public function process(array $requestHeader, array $requestBody): Transcription
    {
        $id = $requestBody['name'];
        $status = self::RESULT_STATUS_MAPPING[$requestBody['status']];
        $result = null;

        if ($status === TranscriptionStatusEnum::COMPLETED) {
            $result = $requestBody['transcript'];
        }

        return new Transcription([
            'id' => $id,
            'status' => $status,
            'result' => $result,
        ]);
    }

    /**
     * Set up callback request's HTTP method & URL.
     */
    public function setUp(string $httpMethod, string $url): void
    {
        $this->callbackMethod = $httpMethod;
        $this->callbackUrl = $url;
    }
}
