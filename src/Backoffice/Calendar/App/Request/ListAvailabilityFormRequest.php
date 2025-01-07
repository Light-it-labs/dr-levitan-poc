<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\App\Request;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\AvailabilityRequestDto;

class ListAvailabilityFormRequest extends FormRequest
{
    public const FROM_DATE = 'fromDate';

    public const TO_DATE = 'toDate';

    /**
     * @throws ValidationException
     */
    public function rules(): array
    {
        return [
            self::FROM_DATE => [
                'required',
                'date',
                'date_format:Y-m-d',
            ],
            self::TO_DATE => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:fromDate'],
        ];
    }

    public function messages(): array
    {
        return [
            'toDate.after_or_equal' => 'The to date must be a date after or equal to the from date.',
        ];
    }

    public function toDto(): AvailabilityRequestDto
    {
        return new AvailabilityRequestDto(
            fromDate: Carbon::parse((string) $this->get(self::FROM_DATE)),
            toDate: Carbon::parse((string) $this->get(self::TO_DATE)),
        );
    }
}
