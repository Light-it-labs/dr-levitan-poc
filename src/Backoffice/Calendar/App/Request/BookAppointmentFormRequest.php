<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\App\Request;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\BookAppointmentDto;

class BookAppointmentFormRequest extends FormRequest
{
    public const TITLE = 'title';

    public const START_DATE_TIME = 'startDateTime';

    public const DURATION = 'duration';

    /**
     * @throws ValidationException
     */
    public function rules(): array
    {
        return [
            self::TITLE => [
                'required',
                'string',
            ],
            self::START_DATE_TIME => [
                'required',
                'date',
                'date_format:Y-m-d\TH:i:sP',
            ],
            self::DURATION => [
                'required',
                'integer',
            ],
        ];
    }

    public function toDto(): BookAppointmentDto
    {
        return new BookAppointmentDto(
            title: $this->string($this::TITLE)->toString(),
            startDateTime: Carbon::parse((string) $this->get(self::START_DATE_TIME)),
            duration: $this->integer(self::DURATION),
        );
    }
}
