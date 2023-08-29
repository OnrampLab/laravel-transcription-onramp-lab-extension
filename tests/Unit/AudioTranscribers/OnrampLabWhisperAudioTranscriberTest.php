<?php

namespace OnrampLab\TranscriptionOnrampLabExtension\Tests\Unit\AudioTranscribers;

use Aws\Lambda\LambdaClient;
use Aws\Result;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Models\Transcript;
use OnrampLab\Transcription\ValueObjects\Transcription;
use OnrampLab\TranscriptionOnrampLabExtension\AudioTranscribers\OnrampLabWhisperAudioTranscriber as BaseAudioTranscriber;
use OnrampLab\TranscriptionOnrampLabExtension\Tests\TestCase;

class OnrampLabWhisperAudioTranscriber extends BaseAudioTranscriber
{
    public function __construct(array $config, LambdaClient $client)
    {
        parent::__construct($config);
        $this->client = $client;
    }

    public function getCallbackMethod(): string
    {
        return $this->callbackMethod;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }
}

class OnrampLabWhisperAudioTranscriberTest extends TestCase
{
    private array $config;

    private string $audioUrl;

    private string $languageCode;

    private MockInterface $clientMock;

    private OnrampLabWhisperAudioTranscriber $transcriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'driver' => 'onramp_lab_whisper',
            'access_key' => Str::upper(Str::random(20)),
            'access_secret' => Str::random(40),
            'region' => 'us-east-1',
        ];
        $this->audioUrl = 'https://example.s3.amazonaws.com/recordings/test.wav';
        $this->languageCode = 'en-US';

        $this->clientMock = Mockery::mock(LambdaClient::class);
        $this->transcriber = new OnrampLabWhisperAudioTranscriber($this->config, $this->clientMock);
    }

    /**
     * @test
     */
    public function transcribe_should_work(): void
    {
        $result = new Result([]);
        $callbackMethod = 'PUT';
        $callbackUrl = 'https://www.example.com/audio/callback';
        $maxSpeakerCount = 2;

        $this->clientMock
            ->shouldReceive('invoke')
            ->once()
            ->withArgs(function (array $args) use ($callbackMethod, $callbackUrl, $maxSpeakerCount) {
                $payload = json_decode($args['Payload'], true);

                return $args['FunctionName'] === 'open-ai-whisper-transcribe:v2'
                    && $args['InvocationType'] === 'Event'
                    && Str::isUuid($payload['name'])
                    && $payload['audio_url'] === $this->audioUrl
                    && $payload['language_code'] === 'en'
                    && $payload['callback_method'] === $callbackMethod
                    && $payload['callback_url'] === $callbackUrl
                    && $payload['enable_speaker_identification'] === true
                    && $payload['max_speaker_count'] === $maxSpeakerCount;
            })
            ->andReturn($result);

        $this->transcriber->setUp($callbackMethod, $callbackUrl);

        $transcription = $this->transcriber->transcribe($this->audioUrl, $this->languageCode, $maxSpeakerCount);

        $this->assertTrue(Str::isUuid($transcription->id));
        $this->assertEquals($transcription->status, TranscriptionStatusEnum::PROCESSING);
    }

    /**
     * @test
     */
    public function parse_should_work(): void
    {
        $transcript = Transcript::factory()->create();
        $transcriptOutput = file_get_contents(__DIR__ . '/Data/onramp_lab_whisper_transcript_output.json');
        $transcription = new Transcription([
            'id' => $transcript->external_id,
            'status' => TranscriptionStatusEnum::COMPLETED,
            'result' => json_decode($transcriptOutput, true),
        ]);

        $this->transcriber->parse($transcription, $transcript);

        $transcript->refresh();

        $this->assertEquals($transcript->segments->count(), 3);
        $this->assertEquals($transcript->segments[0]->content, "One of the most famous landmarks on the borders, it's three hills and the myth is that Merlin, the magician, split one hill into three and left two hills at the back of us which you can see.");
        $this->assertEquals($transcript->segments[0]->start_time, '00:00:00.520');
        $this->assertEquals($transcript->segments[0]->end_time, '00:00:10.860');
        $this->assertEquals($transcript->segments[0]->speaker_label, 'speaker_1');
        $this->assertEquals($transcript->segments[1]->content, "The weather's never good though, we stay on the borders if the mist's on the Yieldens, we never get the good weather. And as you can see today, there's no sunshine. It's a typical Scottish Borders day.");
        $this->assertEquals($transcript->segments[1]->start_time, '00:00:11.140');
        $this->assertEquals($transcript->segments[1]->end_time, '00:00:19.880');
        $this->assertEquals($transcript->segments[1]->speaker_label, 'speaker_1');
        $this->assertEquals($transcript->segments[2]->content, "Fantastic!");
        $this->assertEquals($transcript->segments[2]->start_time, '00:00:21.540');
        $this->assertEquals($transcript->segments[2]->end_time, '00:00:22.120');
        $this->assertEquals($transcript->segments[2]->speaker_label, 'speaker_1');
    }

    /**
     * @test
     */
    public function validate_should_work(): void
    {
        $transcriptOutput = json_decode(file_get_contents(__DIR__ . '/Data/onramp_lab_whisper_transcript_output.json'), true);
        $requestHeader = [
            'host' => ['www.example.com'],
            'connection' => ['keep-alive'],
            'user-agent' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'],
        ];
        $requestBody = [
            'name' => Str::uuid()->toString(),
            'status' => 'completed',
            'transcript' => $transcriptOutput,
        ];

        $this->transcriber->validate($requestHeader, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @test
     */
    public function process_should_work(): void
    {
        $transcriptOutput = json_decode(file_get_contents(__DIR__ . '/Data/onramp_lab_whisper_transcript_output.json'), true);
        $requestHeader = [
            'host' => ['www.example.com'],
            'connection' => ['keep-alive'],
            'user-agent' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'],
        ];
        $requestBody = [
            'name' => Str::uuid()->toString(),
            'status' => 'completed',
            'transcript' => $transcriptOutput,
        ];

        $transcription = $this->transcriber->process($requestHeader, $requestBody);

        $this->assertEquals($transcription->id, $requestBody['name']);
        $this->assertEquals($transcription->status, TranscriptionStatusEnum::COMPLETED);
        $this->assertEquals($transcription->result, $requestBody['transcript']);
    }

    /**
     * @test
     */
    public function set_up_should_work(): void
    {
        $callbackMethod = 'PUT';
        $callbackUrl = 'https://www.example.com/audio/callback';

        $this->transcriber->setUp($callbackMethod, $callbackUrl);

        $this->assertEquals($this->transcriber->getCallbackMethod(), $callbackMethod);
        $this->assertEquals($this->transcriber->getCallbackUrl(), $callbackUrl);
    }
}
