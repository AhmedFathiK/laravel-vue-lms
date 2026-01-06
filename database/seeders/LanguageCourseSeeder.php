<?php

namespace Database\Seeders;

use App\Models\Concept;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\Slide;
use App\Models\SubscriptionPlan;
use App\Models\Term;
use Illuminate\Database\Seeder;

class LanguageCourseSeeder extends Seeder
{
    public function run(): void
    {
        $category = CourseCategory::firstOrCreate(
            ['name->en' => 'Languages'],
            ['name' => ['en' => 'Languages', 'es' => 'Idiomas', 'fr' => 'Langues', 'de' => 'Sprachen', 'ar' => 'اللغات']]
        );

        $coursesData = $this->getCoursesData();

        foreach ($coursesData as $courseData) {
            // Check if course already exists
            $existingCourse = Course::where('title->en', $courseData['title']['en'])->first();
            if ($existingCourse) {
                $this->command->info("Deleting existing course to re-seed: {$courseData['title']['en']}");
                $existingCourse->forceDelete(); // Force delete to remove related data via cascades if setup, or just delete
                // Note: SoftDeletes might keep it. Using forceDelete.
            }

            $this->command->info("Creating Course: {$courseData['title']['en']}");

            // Create Course
            $course = Course::create([
                'title' => $courseData['title'],
                'description' => $courseData['description'],
                'course_category_id' => $category->id,
                'main_locale' => $courseData['locale'],
                'status' => 'published',
                'is_featured' => true,
            ]);

            // Create Subscription Plans
            SubscriptionPlan::create([
                'course_id' => $course->id,
                'name' => 'Free Plan',
                'description' => 'Access to basic content',
                'price' => 0,
                'currency' => 'USD',
                'is_free' => true,
                'plan_type' => 'free',
                'billing_cycle' => 'lifetime',
                'is_active' => true,
            ]);

            SubscriptionPlan::create([
                'course_id' => $course->id,
                'name' => 'Premium Plan',
                'description' => 'Full access to all levels',
                'price' => 19.99,
                'currency' => 'USD',
                'is_free' => false,
                'plan_type' => 'recurring',
                'billing_cycle' => 'monthly',
                'is_active' => true,
            ]);

            // Create Levels
            foreach ($courseData['levels'] as $levelIndex => $levelData) {
                $level = Level::create([
                    'course_id' => $course->id,
                    'title' => $levelData['title'], // Translatable array
                    'description' => ['en' => "Level " . ($levelIndex + 1), 'ar' => "المستوى " . ($levelIndex + 1)],
                    'sort_order' => $levelIndex + 1,
                    'is_free' => $levelIndex === 0, // First level is free
                    'status' => 'published',
                ]);

                // Create Lessons
                foreach ($levelData['lessons'] as $lessonIndex => $lessonData) {
                    $lesson = Lesson::create([
                        'level_id' => $level->id,
                        'title' => $lessonData['title'], // Translatable array
                        'description' => $lessonData['description'], // Translatable array
                        'sort_order' => $lessonIndex + 1,
                        'is_free' => $levelIndex === 0 && $lessonIndex < 2, // First 2 lessons of level 1 are free
                        'status' => 'published',
                    ]);

                    // Create Terms
                    $createdTerms = [];
                    foreach ($lessonData['terms'] as $termData) {
                        $term = Term::create([
                            'course_id' => $course->id,
                            'term' => $termData['term']['en'] ?? reset($termData['term']), // Not translatable now, take English or first
                            'meaning' => $termData['meaning'], // Renamed from definition
                            'example' => is_array($termData['example']) ? ($termData['example']['en'] ?? reset($termData['example'])) : $termData['example'],
                            'example_translation' => $termData['example_translation'],
                        ]);
                        $createdTerms[] = $term;
                    }

                    // Create Concepts
                    $createdConcepts = [];
                    foreach ($lessonData['concepts'] ?? [] as $conceptData) {
                        $concept = Concept::create([
                            'course_id' => $course->id,
                            'lesson_id' => $lesson->id,
                            'title' => $conceptData['title'],
                            'explanation' => $conceptData['explanation'],
                            'type' => 'grammar',
                        ]);
                        $createdConcepts[] = $concept;
                    }

                    // Create Slides
                    $this->createSlides($lesson, $createdTerms, $createdConcepts, $course);
                }
            }
        }
    }

    private function createSlides($lesson, $terms, $concepts, $course)
    {
        $slideOrder = 1;

        // 1. Introduction Slide (Explanation)
        Slide::create([
            'lesson_id' => $lesson->id,
            'title' => ['en' => 'Introduction', 'ar' => 'مقدمة'],
            'type' => 'explanation',
            'content' => [
                'en' => "Welcome to this lesson! Today we will learn about " . ($lesson->getTranslation('title', 'en')),
                'ar' => "مرحباً بك في هذا الدرس! اليوم سنتعلم عن " . ($lesson->getTranslation('title', 'ar') ?? $lesson->getTranslation('title', 'en')),
            ],
            'sort_order' => $slideOrder++,
        ]);

        // 2. Teach Concepts and Test
        foreach ($concepts as $concept) {
            // Concept Explanation Slide
            Slide::create([
                'lesson_id' => $lesson->id,
                'title' => [
                    'en' => 'Concept: ' . $concept->getTranslation('title', 'en'),
                    'ar' => 'مفهوم: ' . ($concept->getTranslation('title', 'ar') ?? $concept->getTranslation('title', 'en'))
                ],
                'type' => 'explanation',
                'content' => [
                    'en' => "Concept: " . $concept->getTranslation('title', 'en') . "\n\n" . $concept->getTranslation('explanation', 'en'),
                    'ar' => "مفهوم: " . ($concept->getTranslation('title', 'ar') ?? '') . "\n\n" . ($concept->getTranslation('explanation', 'ar') ?? ''),
                ],
                'sort_order' => $slideOrder++,
            ]);

            // Question for Concept (MCQ)
            $qTitle = [
                'en' => 'Test: ' . $concept->getTranslation('title', 'en'),
                'ar' => 'اختبار: ' . ($concept->getTranslation('title', 'ar') ?? $concept->getTranslation('title', 'en'))
            ];

            $options = [
                'en' => ['Correct usage example', 'Incorrect usage example'],
                'ar' => ['مثال استخدام صحيح', 'مثال استخدام خاطئ']
            ];

            $questionContent = [
                'en' => [
                    'options' => $options['en'],
                    'correctAnswer' => ["0"]
                ],
                'ar' => [
                    'options' => $options['ar'],
                    'correctAnswer' => ["0"]
                ]
            ];

            $question = Question::create([
                'course_id' => $course->id,
                'title' => $qTitle,
                'question_text' => [
                    'en' => 'Which statement correctly applies the rule of ' . $concept->getTranslation('title', 'en') . '?',
                    'ar' => 'أي جملة تطبق قاعدة ' . ($concept->getTranslation('title', 'ar') ?? $concept->getTranslation('title', 'en')) . ' بشكل صحيح؟',
                ],
                'type' => Question::TYPE_MCQ,
                'content' => $questionContent,
                'points' => 10,
            ]);
            $question->concepts()->attach($concept->id);

            // Question Slide
            Slide::create([
                'lesson_id' => $lesson->id,
                'title' => ['en' => 'Concept Quiz', 'ar' => 'اختبار المفهوم'],
                'type' => Question::TYPE_MCQ,
                'question_id' => $question->id,
                'content' => [],
                'sort_order' => $slideOrder++,
            ]);
        }

        // 3. Teach Terms and Test
        $allMeaningsEn = collect($terms)->map(fn($t) => $t->getTranslation('meaning', 'en'))->toArray();
        $allMeaningsAr = collect($terms)->map(fn($t) => $t->getTranslation('meaning', 'ar') ?? $t->getTranslation('meaning', 'en'))->toArray();
        $allTerms = collect($terms)->map(fn($t) => $t->term)->toArray();

        foreach ($terms as $index => $term) {
            // Term Slide
            Slide::create([
                'lesson_id' => $lesson->id,
                'title' => ['en' => 'New Term', 'ar' => 'مصطلح جديد'],
                'type' => 'term',
                'term_id' => $term->id,
                'content' => [],
                'sort_order' => $slideOrder++,
            ]);

            $termText = $term->term;
            $meaningTextEn = $term->getTranslation('meaning', 'en');
            $meaningTextAr = $term->getTranslation('meaning', 'ar') ?? $meaningTextEn;
            $exampleText = $term->example;

            // Cycle Question Types
            $typeIndex = $index % 5;

            $questionType = Question::TYPE_MCQ;
            $questionContent = [];
            $questionText = [];

            switch ($typeIndex) {
                case 0: // MCQ
                    $questionType = Question::TYPE_MCQ;
                    $questionText = [
                        'en' => 'What is the meaning of "' . $termText . '"?',
                        'ar' => 'ما معنى "' . $termText . '"؟'
                    ];

                    // Generate options En
                    $wrongMeaningsEn = array_values(array_diff($allMeaningsEn, [$meaningTextEn]));
                    shuffle($wrongMeaningsEn);
                    $distractorsEn = array_slice($wrongMeaningsEn, 0, 3);
                    $optionsEn = array_merge([$meaningTextEn], $distractorsEn);

                    // Generate options Ar
                    $wrongMeaningsAr = array_values(array_diff($allMeaningsAr, [$meaningTextAr]));
                    shuffle($wrongMeaningsAr);
                    $distractorsAr = array_slice($wrongMeaningsAr, 0, 3);
                    $optionsAr = array_merge([$meaningTextAr], $distractorsAr);

                    $questionContent = [
                        'en' => [
                            'options' => $optionsEn,
                            'correctAnswer' => ["0"]
                        ],
                        'ar' => [
                            'options' => $optionsAr,
                            'correctAnswer' => ["0"]
                        ]
                    ];
                    break;

                case 1: // Fill Blank
                    $questionType = Question::TYPE_FILL_BLANK;
                    // Replace term in example with blank
                    $qTextEn = str_replace($termText, '[blank1]', $exampleText);
                    if ($qTextEn === $exampleText) {
                        $qTextEn = "Translate: " . $meaningTextEn . " -> [blank1]";
                    }

                    $questionText = [
                        'en' => $qTextEn,
                        'ar' => $qTextEn . ' (أكمل الفراغ)' // Simple fallback
                    ];

                    $questionContent = [
                        'en' => ['correctAnswer' => [[$termText]]],
                        'ar' => ['correctAnswer' => [[$termText]]]
                    ];
                    break;

                case 2: // Reordering
                    $questionType = Question::TYPE_REORDERING;
                    $questionText = [
                        'en' => "Arrange the words to form the correct sentence.",
                        'ar' => "رتب الكلمات لتكوين جملة صحيحة."
                    ];
                    $words = explode(' ', $exampleText);
                    $questionContent = [
                        'en' => ['items' => $words],
                        'ar' => ['items' => $words]
                    ];
                    break;

                case 3: // Fill Blank Choices
                    $questionType = Question::TYPE_FILL_BLANK_CHOICES;
                    $qTextEn = str_replace($termText, '[blank1]', $exampleText);
                    if ($qTextEn === $exampleText) {
                        $qTextEn = "Select the correct word: [blank1]";
                    }
                    $questionText = [
                        'en' => $qTextEn,
                        'ar' => $qTextEn . ' (اختر الكلمة الصحيحة)'
                    ];

                    $wrongTerms = array_values(array_diff($allTerms, [$termText]));
                    shuffle($wrongTerms);
                    $distractor = $wrongTerms[0] ?? 'Wrong';

                    $choices = [$termText, $distractor];

                    $questionContent = [
                        'en' => [
                            'blanks' => [
                                [
                                    'placeholder' => 'blank1',
                                    'options' => $choices,
                                    'correct_answer' => "0"
                                ]
                            ]
                        ],
                        'ar' => [
                            'blanks' => [
                                [
                                    'placeholder' => 'blank1',
                                    'options' => $choices,
                                    'correct_answer' => "0"
                                ]
                            ]
                        ]
                    ];
                    break;

                case 4: // Writing
                    $questionType = Question::TYPE_WRITING;
                    $questionText = [
                        'en' => 'Write a sentence using the word "' . $termText . '".',
                        'ar' => 'اكتب جملة باستخدام الكلمة "' . $termText . '".'
                    ];
                    $questionContent = [
                        'en' => [
                            'grading_guidelines' => "Check for correct usage of $termText.",
                            'min_words' => "3",
                            'max_words' => "20"
                        ],
                        'ar' => [
                            'grading_guidelines' => "Check for correct usage of $termText.",
                            'min_words' => "3",
                            'max_words' => "20"
                        ]
                    ];
                    break;
            }

            $question = Question::create([
                'course_id' => $course->id,
                'title' => ['en' => 'Test: ' . $termText, 'ar' => 'اختبار: ' . $termText],
                'question_text' => $questionText,
                'type' => $questionType,
                'content' => $questionContent,
                'points' => 10,
            ]);
            $question->terms()->attach($term->id);

            // Question Slide
            Slide::create([
                'lesson_id' => $lesson->id,
                'title' => ['en' => 'Quiz', 'ar' => 'اختبار'],
                'type' => $questionType,
                'question_id' => $question->id,
                'content' => [],
                'sort_order' => $slideOrder++,
            ]);
        }

        // 4. End of Lesson Matching
        // Create matching pairs for all terms in lesson
        $pairs = [];
        foreach ($terms as $term) {
            $pairs[] = [
                'left' => $term->term,
                'right' => $term->getTranslation('meaning', 'en')
            ];
        }

        $matchingQuestion = Question::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Lesson Review: Matching', 'ar' => 'مراجعة الدرس: مطابقة'],
            'question_text' => ['en' => 'Match the terms to their meanings.', 'ar' => 'طابق المصطلحات مع معانيها.'],
            'type' => Question::TYPE_MATCHING,
            'content' => [
                'en' => ['pairs' => $pairs],
                'ar' => ['pairs' => $pairs] // Ideally pairs should be translated, but using same pairs for now
            ],
            'points' => 20,
        ]);
        // Attach all terms
        $matchingQuestion->terms()->attach(collect($terms)->pluck('id'));

        Slide::create([
            'lesson_id' => $lesson->id,
            'title' => ['en' => 'Review Matching', 'ar' => 'مراجعة المطابقة'],
            'type' => Question::TYPE_MATCHING,
            'question_id' => $matchingQuestion->id,
            'content' => [],
            'sort_order' => $slideOrder++,
        ]);
    }

    private function getCoursesData()
    {
        return [
            [
                'locale' => 'en',
                'title' => ['en' => 'English for Beginners', 'de' => 'Englisch für Anfänger', 'fr' => 'Anglais pour débutants', 'ar' => 'الإنجليزية للمبتدئين'],
                'description' => ['en' => 'Learn English from scratch.', 'ar' => 'تعلم الإنجليزية من الصفر.'],
                'levels' => $this->getEnglishCourseData(),
            ],
            [
                'locale' => 'de',
                'title' => ['en' => 'German for Beginners', 'de' => 'Deutsch für Anfänger', 'fr' => 'Allemand pour débutants', 'ar' => 'الألمانية للمبتدئين'],
                'description' => ['en' => 'Learn German from scratch.', 'ar' => 'تعلم الألمانية من الصفر.'],
                'levels' => $this->getLevelsData('de'),
            ],
            [
                'locale' => 'fr',
                'title' => ['en' => 'French for Beginners', 'de' => 'Französisch für Anfänger', 'fr' => 'Français pour débutants', 'ar' => 'الفرنسية للمبتدئين'],
                'description' => ['en' => 'Learn French from scratch.', 'ar' => 'تعلم الفرنسية من الصفر.'],
                'levels' => $this->getLevelsData('fr'),
            ],
        ];
    }

    private function getEnglishCourseData()
    {
        $jsonPath = base_path('course-data.json');

        $levels = [];

        if (file_exists($jsonPath)) {
            $data = json_decode(file_get_contents($jsonPath), true);

            $levelData = [
                'title' => $data['title'],
                'lessons' => []
            ];

            foreach ($data['lessons'] as $lesson) {
                $terms = [];
                foreach ($lesson['terms'] as $term) {
                    $terms[] = [
                        'term' => $term['term'],
                        'meaning' => $term['meaning'],
                        'example' => $term['example']['en'] ?? reset($term['example']),
                        'example_translation' => $term['example'],
                    ];
                }

                $concepts = [];
                if (isset($lesson['concept'])) {
                    $concepts[] = [
                        'title' => $lesson['concept']['title'],
                        'explanation' => $lesson['concept']['explanation'],
                    ];
                }

                $levelData['lessons'][] = [
                    'title' => $lesson['title'],
                    'description' => $lesson['description'],
                    'terms' => $terms,
                    'concepts' => $concepts,
                ];
            }
            $levels[] = $levelData;
        }

        // Add more levels if we have fewer than 3
        $remainingLevels = $this->getLevelsData('en');
        foreach ($remainingLevels as $index => $level) {
            if (count($levels) >= 3) break;

            // If we added Level 1 from JSON, skip Level 1 from dummy data
            if (count($levels) === 1 && $index === 0) continue;

            $levels[] = $level;
        }

        return $levels;
    }


    private function getLevelsData($lang)
    {
        $levels = [];
        for ($i = 1; $i <= 3; $i++) {
            $levelTitle = match ($lang) {
                'de' => "Stufe $i",
                'fr' => "Niveau $i",
                default => "Level $i",
            };

            $arTitle = "المستوى $i";

            $levels[] = [
                'title' => ['en' => "Level $i", $lang => $levelTitle, 'ar' => $arTitle],
                'lessons' => $this->getLessonsData($lang, $i),
            ];
        }
        return $levels;
    }

    private function getLessonsData($lang, $levelIndex)
    {
        // 5 fixed topics
        $topics = [
            1 => ['en' => 'Greetings', 'de' => 'Begrüßungen', 'fr' => 'Salutations', 'ar' => 'التحيات'],
            2 => ['en' => 'Health', 'de' => 'Gesundheit', 'fr' => 'Santé', 'ar' => 'الصحة'],
            3 => ['en' => 'Occupations', 'de' => 'Berufe', 'fr' => 'Métiers', 'ar' => 'المهن'],
            4 => ['en' => 'Countries', 'de' => 'Länder', 'fr' => 'Pays', 'ar' => 'الدول'],
            5 => ['en' => 'Food', 'de' => 'Essen', 'fr' => 'Nourriture', 'ar' => 'الطعام'],
        ];

        $lessons = [];
        foreach ($topics as $index => $titles) {
            $lessons[] = [
                'title' => ['en' => $titles['en'], $lang => $titles[$lang], 'ar' => $titles['ar']],
                'description' => ['en' => "Learn about " . $titles['en'], 'ar' => "تعلم عن " . $titles['ar']],
                'terms' => $this->getTermsForTopic($titles['en'], $lang),
                'concepts' => $this->getConceptsForTopic($titles['en'], $lang),
            ];
        }
        return $lessons;
    }

    private function getTermsForTopic($topic, $lang)
    {
        // Simple dictionary for demo
        $data = [
            'Greetings' => [
                ['en' => 'Hello', 'de' => 'Hallo', 'fr' => 'Bonjour', 'def_en' => 'A greeting', 'def_ar' => 'تحية'],
                ['en' => 'Good morning', 'de' => 'Guten Morgen', 'fr' => 'Bonjour', 'def_en' => 'Greeting in the morning', 'def_ar' => 'تحية صباحية'],
                ['en' => 'Goodbye', 'de' => 'Auf Wiedersehen', 'fr' => 'Au revoir', 'def_en' => 'Farewell', 'def_ar' => 'وداع'],
            ],
            'Health' => [
                ['en' => 'Doctor', 'de' => 'Arzt', 'fr' => 'Médecin', 'def_en' => 'Medical professional', 'def_ar' => 'طبيب محترف'],
                ['en' => 'Sick', 'de' => 'Krank', 'fr' => 'Malade', 'def_en' => 'Not feeling well', 'def_ar' => 'لا يشعر بخير'],
            ],
            'Occupations' => [
                ['en' => 'Teacher', 'de' => 'Lehrer', 'fr' => 'Professeur', 'def_en' => 'Someone who teaches', 'def_ar' => 'شخص يقوم بالتدريس'],
                ['en' => 'Engineer', 'de' => 'Ingenieur', 'fr' => 'Ingénieur', 'def_en' => 'Someone who designs structures', 'def_ar' => 'شخص يصمم الهياكل'],
            ],
            'Countries' => [
                ['en' => 'Germany', 'de' => 'Deutschland', 'fr' => 'Allemagne', 'def_en' => 'A country in Europe', 'def_ar' => 'دولة في أوروبا'],
                ['en' => 'France', 'de' => 'Frankreich', 'fr' => 'France', 'def_en' => 'A country in Europe', 'def_ar' => 'دولة في أوروبا'],
            ],
            'Food' => [
                ['en' => 'Apple', 'de' => 'Apfel', 'fr' => 'Pomme', 'def_en' => 'A fruit', 'def_ar' => 'فاكهة'],
                ['en' => 'Bread', 'de' => 'Brot', 'fr' => 'Pain', 'def_en' => 'Baked food', 'def_ar' => 'طعام مخبوز'],
            ],
        ];

        $prefix = match ($lang) {
            'de' => 'Das ist ',
            'fr' => "C'est ",
            default => 'This is ',
        };

        $terms = [];
        foreach ($data[$topic] ?? [] as $item) {
            $terms[] = [
                'term' => [$lang => $item[$lang]], // The term itself in target lang
                'meaning' => ['en' => $item['def_en'], 'ar' => $item['def_ar'] ?? ''], // Meaning in English and Arabic
                'example' => $prefix . $item[$lang],
                'example_translation' => ['en' => "This is " . $item['en'], 'ar' => "هذا " . $item['def_ar']], // Simplified example translation
            ];
        }
        return $terms;
    }

    private function getConceptsForTopic($topic, $lang)
    {
        // Mock concepts
        return [
            [
                'title' => ['en' => 'Basic Grammar', $lang => 'Grammatik', 'ar' => 'قواعد أساسية'],
                'explanation' => [
                    'en' => "Here is a grammar rule about $topic in " . strtoupper($lang),
                    'ar' => "هذه قاعدة نحوية حول $topic في " . strtoupper($lang)
                ],
            ]
        ];
    }
}
