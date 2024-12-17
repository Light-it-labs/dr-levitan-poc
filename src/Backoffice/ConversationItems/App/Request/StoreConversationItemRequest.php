<?php

declare(strict_types=1);

namespace Lightit\Backoffice\ConversationItems\App\Request;

use Illuminate\Foundation\Http\FormRequest;
use Lightit\Backoffice\ConversationItems\Domain\DataTransferObjects\ConversationItemDto;

class StoreConversationItemRequest extends FormRequest
{
    public const EVENT_TIME = 'eventTime';

    public const OBJECT = 'object';

    public const TYPE = 'type';

    public const DATA = 'data';

    public const DATA_OBJECT = 'data.object';

    public const TEXT = 'data.object.text';

    public const APP_URL = 'data.object.appURL';

    public const AUTHOR_DISPLAY_NAME = 'data.object.author.displayName';

    public const CREATED_AT = 'data.object.createdAt';

    public const ID = 'data.object.id';

    public const CONVERSATION_ID = 'data.object.conversationId';

    public const DIRECTION = 'data.object.direction';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            self::EVENT_TIME => ['required', 'date'],
            self::OBJECT => ['required', 'string', 'in:event'],
            self::TYPE => ['required', 'string', 'in:conversationItem.created'],
            self::DATA => ['required', 'array'],
            self::DATA_OBJECT => ['required', 'array'],
            self::TEXT => ['nullable', 'string'],
            self::APP_URL => ['required', 'string'],
            self::AUTHOR_DISPLAY_NAME => ['nullable', 'string'],
            self::CREATED_AT => ['required', 'date'],
            self::ID => ['required', 'string'],
            self::CONVERSATION_ID => ['required', 'string'],
            self::DIRECTION => ['required', 'string', 'in:inbound,outbound'],
        ];
    }

    public function toDto(): ConversationItemDto
    {
        return new ConversationItemDto(
            eventTime: $this->string(self::EVENT_TIME)->toString(),
            type: $this->string(self::TYPE)->toString(),
            text: is_string($this->input(self::TEXT)) ? $this->string(self::TEXT)->toString() : '',
            appURL: is_string($this->input(self::APP_URL)) ? $this->string(self::APP_URL)->toString() : '',
            authorDisplayName: is_string($this->input(self::AUTHOR_DISPLAY_NAME)) ? $this->string(
                self::AUTHOR_DISPLAY_NAME
            )->toString() : '',
            createdAt: $this->string(self::CREATED_AT)->toString(),
            id: $this->string(self::ID)->toString(),
            conversationId: $this->string(self::CONVERSATION_ID)->toString(),
            direction: $this->string(self::DIRECTION)->toString(),
        );
    }
}
