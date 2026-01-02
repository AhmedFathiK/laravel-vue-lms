<?php

namespace App\Http\Requests\Admin\Question;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.questions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'question_text' => ['required', 'string'],
            'type' => ['required', Rule::in([
                Question::TYPE_MCQ,
                Question::TYPE_MATCHING,
                Question::TYPE_FILL_BLANK,
                Question::TYPE_REORDERING,
                Question::TYPE_FILL_BLANK_CHOICES,
                Question::TYPE_WRITING,
            ])],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'array'],
            'blanks' => ['nullable', 'array'],
            'matching_pairs' => ['nullable', 'array'],
            'reordering_items' => ['nullable', 'array'],
            'grading_guidelines' => ['nullable', 'string'],
            'min_words' => ['nullable', 'integer', 'min:0'],
            'max_words' => ['nullable', 'integer', 'min:0'],
            'points' => ['sometimes', 'required', 'integer', 'min:1'],
            'difficulty' => ['sometimes', 'required', Rule::in(['easy', 'medium', 'hard'])],
            'course_id' => ['nullable', 'exists:courses,id'],
            'title' => ['nullable', 'string'],
            'correct_feedback' => ['nullable', 'string'],
            'incorrect_feedback' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],

            'media_type' => [Rule::in(['none', 'image', 'image_with_audio', 'video'])],
            'media_url' => ['nullable', 'string', 'max:255'],
            'audio_url' => ['nullable', 'string', 'max:255'],
            'media' => ['nullable', 'file', 'max:10240'], // 10MB max file size
        ];

        // Add specific rules for different question types
        $type = $this->input('type');

        if ($type) {
            switch ($type) {
                case Question::TYPE_MCQ:
                    $rules['options'] = ['sometimes', 'required', 'array', 'min:2'];
                    $rules['options.*'] = ['required', 'string'];
                    $rules['correct_answer'] = ['sometimes', 'required', 'array', 'min:1'];
                    $rules['correct_answer.*'] = ['required', 'string'];
                    break;

                case Question::TYPE_FILL_BLANK:
                    $rules['correct_answer'] = ['sometimes', 'required', 'array', 'min:1'];
                    $rules['correct_answer.*'] = ['required', 'array', 'min:1'];
                    $rules['correct_answer.*.*'] = ['required', 'string'];
                    break;

                case Question::TYPE_FILL_BLANK_CHOICES:
                    $rules['blanks'] = ['sometimes', 'required', 'array', 'min:1'];
                    $rules['blanks.*.placeholder'] = ['nullable', 'string'];
                    $rules['blanks.*.options'] = ['required', 'array', 'min:2'];
                    $rules['blanks.*.options.*'] = ['required', 'string'];
                    $rules['blanks.*.correct_answer'] = ['required', 'string'];
                    break;

                case Question::TYPE_MATCHING:
                    $rules['matching_pairs'] = ['sometimes', 'required', 'array', 'min:2'];
                    $rules['matching_pairs.*.left'] = ['required', 'string'];
                    $rules['matching_pairs.*.right'] = ['required', 'string'];
                    break;

                case Question::TYPE_REORDERING:
                    $rules['reordering_items'] = ['sometimes', 'required', 'array', 'min:2'];
                    $rules['reordering_items.*'] = ['required', 'string'];
                    break;

                case Question::TYPE_WRITING:
                    $rules['grading_guidelines'] = ['nullable', 'string'];
                    $rules['min_words'] = ['nullable', 'integer', 'min:0'];
                    $rules['max_words'] = ['nullable', 'integer', 'min:0'];
                    break;
            }
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate fill in the blank questions
            if ($this->input('type') === Question::TYPE_FILL_BLANK) {
                $questionText = $this->input('question_text', '');
                $answers = $this->input('correct_answer', []);

                // Count the number of [blankX] occurrences
                preg_match_all('/\[blank\d+\]/', $questionText, $matches);
                $blankCount = count(array_unique($matches[0]));

                if ($blankCount !== count($answers)) {
                    $validator->errors()->add('correct_answer', 'The number of answers must match the number of blanks in the question text.');
                }
            }

            // Validate media requirements based on media_type
            $mediaType = $this->input('media_type');

            if ($mediaType && $mediaType !== 'none') {
                // For updates, we only validate if a new file is being uploaded
                if ($this->hasFile('media')) {
                    // Validate image media types
                    if ($mediaType === 'image' || $mediaType === 'image_with_audio') {
                        $file = $this->file('media');
                        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                        if (!in_array($file->getMimeType(), $allowedImageTypes)) {
                            $validator->errors()->add('media', 'The media file must be an image (jpeg, png, webp, gif).');
                        }
                    }
                }

                // For video, check if URL is provided
                if ($mediaType === 'video' && !$this->input('media_url') && $this->isChangingMediaType()) {
                    $validator->errors()->add('media_url', 'A video URL is required when video media type is selected.');
                }

                // For image_with_audio, check if audio URL is provided
                if ($mediaType === 'image_with_audio' && !$this->input('audio_url') && ($this->isChangingMediaType() || $this->isChangingToImageWithAudio())) {
                    $validator->errors()->add('audio_url', 'An audio URL is required when image with audio media type is selected.');
                }

                // If changing to an image media type but no file is uploaded and no existing media_url
                if (($mediaType === 'image' || $mediaType === 'image_with_audio') && !$this->hasFile('media') && !$this->input('media_url') && $this->isChangingMediaType()) {
                    $validator->errors()->add('media', 'An image file is required when changing to this media type.');
                }
            }
        });
    }

    /**
     * Check if the media type is being changed from none to a media type
     */
    private function isChangingMediaType(): bool
    {
        $question = $this->route('question');

        if (!$question) {
            return false;
        }

        $oldMediaType = $question->media_type;
        $newMediaType = $this->input('media_type');

        return ($oldMediaType === 'none' || $oldMediaType === null) && $newMediaType !== 'none';
    }

    /**
     * Check if the media type is being changed to image_with_audio
     */
    private function isChangingToImageWithAudio(): bool
    {
        $question = Question::find($this->route('question')->id);

        if (!$question) {
            return false;
        }

        $oldMediaType = $question->media_type;
        $newMediaType = $this->input('media_type');

        return $oldMediaType !== 'image_with_audio' && $newMediaType === 'image_with_audio';
    }

}
