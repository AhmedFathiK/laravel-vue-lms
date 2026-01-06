# Learning Platform API Documentation

## Introduction

This documentation covers the REST API endpoints for the learning platform. The API allows you to manage courses, levels, lessons, slides, terms, and concepts through a structured hierarchy.

## Authentication

All API endpoints require authentication using Laravel Sanctum. Include the following header in your requests:

```
Authorization: Bearer {your_token}
```

## Base URLs

For admin endpoints:
```
{base_url}/api/admin
```

For learner endpoints:
```
{base_url}/api/learner
```

For authentication:
```
{base_url}/api/auth
```

For token management:
```
{base_url}/api/token
```

For gamification:
```
{base_url}/api/gamification
```

For revision system:
```
{base_url}/api/revision
```

## Response Format

All responses are in JSON format. Successful responses have a status code of 200 (OK) or 201 (Created).

Error responses include an error message and appropriate HTTP status code.

# Authentication API Endpoints

## SPA Authentication (Session-based)

### Login

```
POST /api/auth/login
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password",
  "remember": true
}
```

### Register

```
POST /api/auth/register
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password"
}
```

### Logout

```
POST /api/auth/logout
```

### Get Authenticated User

```
GET /api/auth/user
```

### Update User Profile

```
PUT /api/auth/profile
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "user@example.com"
}
```

### Change Password

```
PUT /api/auth/password
```

**Request Body:**
```json
{
  "current_password": "current-password",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

## API Token Authentication

### Create Token

```
POST /api/token/create
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password",
  "device_name": "iPhone 13"
}
```

### Register and Create Token

```
POST /api/token/register
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password",
  "device_name": "iPhone 13"
}
```

### Get Token User

```
GET /api/token/user
```

### Revoke Current Token

```
DELETE /api/token/revoke
```

### Revoke All Tokens

```
DELETE /api/token/revoke-all
```

### Revoke Specific Token

```
DELETE /api/token/revoke/{tokenId}
```

### List User Tokens

```
GET /api/token/list
```

## User Profile

### Get User Profile

```
GET /api/profile
```

### Update User Locale

```
POST /api/user/locale
```

**Request Body:**
```json
{
  "locale": "en"
}
```

# Admin API Endpoints

## Courses

### List All Courses

```
GET /courses
```

**Query Parameters:**
- `status` - Filter by status (draft, published, archived)
- `featured` - Filter by featured status (true/false)
- `sort_field` - Field to sort by (default: sort_order)
- `sort_direction` - Sort direction (asc/desc)
- `per_page` - Items per page (default: 15)

### Get a Specific Course

```
GET /courses/{course_id}
```

### Create a Course

```
POST /courses
```

**Request Body:**
```json
{
  "title": {
    "en": "Course Title"
  },
  "description": {
    "en": "Course description"
  },
  "status": "draft",
  "thumbnail": "path/to/thumbnail.jpg",
  "is_featured": false,
  "sort_order": 1
}
```

### Update a Course

```
PUT /courses/{course_id}
```

**Request Body:** Same as Create

### Delete a Course

```
DELETE /courses/{course_id}
```

## Assessment System

### Questions

#### List Questions (Question Bank)

```
GET /questions
```

**Query Parameters:**
- `course_id` - Filter by course ID
- `level_id` - Filter by level ID
- `lesson_id` - Filter by lesson ID
- `type` - Filter by question type (mcq, matching, fill_blank, reordering, fill_blank_choices, writing)
- `difficulty` - Filter by difficulty (easy, medium, hard)
- `tags` - Filter by tags (comma-separated)
- `sort_by` - Field to sort by (default: created_at)
- `sort_direction` - Sort direction (asc/desc)
- `per_page` - Items per page (default: 15)

#### Get a Specific Question

```
GET /questions/{question_id}
```

#### Create a Question

```
POST /questions
```

**Request Body:**
```json
{
  "course_id": 1,
  "level_id": 2,
  "lesson_id": 3,
  "question_text": {
    "en": "What is the capital of France?"
  },
  "type": "mcq",
  "options": ["London", "Paris", "Berlin", "Madrid"],
  "correct_answer": ["Paris"],
  "points": 1,
  "difficulty": "easy",
  "tags": ["geography", "europe"],
  "explanation": {
    "en": "Paris is the capital of France."
  },
  "media_url": "path/to/image.jpg",
  "media_type": "image"
}
```

#### Update a Question

```
PUT /questions/{question_id}
```

**Request Body:** Same as Create

#### Delete a Question

```
DELETE /questions/{question_id}
```

### Exams

#### List Exams

```
GET /exams
```

**Query Parameters:**
- `course_id` - Filter by course ID
- `level_id` - Filter by level ID
- `lesson_id` - Filter by lesson ID
- `type` - Filter by exam type (lesson_quiz, level_end, course_end, placement)
- `status` - Filter by status (draft, published, archived)
- `is_active` - Filter by active status (true/false)
- `sort_by` - Field to sort by (default: created_at)
- `sort_direction` - Sort direction (asc/desc)
- `per_page` - Items per page (default: 15)

#### Get a Specific Exam

```
GET /exams/{exam_id}
```

#### Create an Exam

```
POST /exams
```

**Request Body:**
```json
{
  "title": {
    "en": "Final Course Exam"
  },
  "description": {
    "en": "Test your knowledge of the entire course"
  },
  "instructions": {
    "en": "Answer all questions. You need 70% to pass."
  },
  "course_id": 1,
  "level_id": null,
  "lesson_id": null,
  "type": "course_end",
  "time_limit": 60,
  "passing_percentage": 70,
  "max_attempts": 3,
  "is_active": true,
  "randomize_questions": false,
  "show_answers": true,
  "status": "published"
}
```

#### Update an Exam

```
PUT /exams/{exam_id}
```

**Request Body:** Same as Create

#### Delete an Exam

```
DELETE /exams/{exam_id}
```

### Exam Sections

#### List Exam Sections

```
GET /exam-sections?exam_id={exam_id}
```

#### Get a Specific Exam Section

```
GET /exam-sections/{section_id}
```

#### Create an Exam Section

```
POST /exam-sections
```

**Request Body:**
```json
{
  "exam_id": 1,
  "title": {
    "en": "Reading Comprehension"
  },
  "description": {
    "en": "Read the passage and answer the questions"
  },
  "instructions": {
    "en": "Answer all questions based on the passage"
  },
  "order": 1,
  "media_url": "path/to/reading_passage.txt",
  "media_type": "reading_passage",
  "time_limit": 20,
  "questions": [
    {
      "id": 1,
      "order": 1
    },
    {
      "id": 2,
      "order": 2
    }
  ]
}
```

#### Update an Exam Section

```
PUT /exam-sections/{section_id}
```

**Request Body:** Same as Create

#### Delete an Exam Section

```
DELETE /exam-sections/{section_id}
```

#### Add a Question to a Section

```
POST /exam-sections/{section_id}/questions
```

**Request Body:**
```json
{
  "question_id": 3,
  "order": 3
}
```

#### Remove a Question from a Section

```
DELETE /exam-sections/{section_id}/questions/{question_id}
```

#### Reorder Questions in a Section

```
POST /exam-sections/{section_id}/reorder-questions
```

**Request Body:**
```json
{
  "questions": [
    {
      "id": 1,
      "order": 2
    },
    {
      "id": 2,
      "order": 1
    },
    {
      "id": 3,
      "order": 3
    }
  ]
}
```

### Exam Responses (Manual Grading)

#### List Pending Writing Responses

```
GET /exam-responses/pending
```

**Query Parameters:**
- `course_id` - Filter by course ID
- `exam_id` - Filter by exam ID
- `user_id` - Filter by user ID
- `sort_by` - Field to sort by (default: created_at)
- `sort_direction` - Sort direction (asc/desc)
- `per_page` - Items per page (default: 15)

#### Get a Specific Response

```
GET /exam-responses/{response_id}
```

#### Grade a Writing Response

```
POST /exam-responses/{response_id}/grade
```

**Request Body:**
```json
{
  "score": 8,
  "feedback": "Good points made, but some grammatical errors."
}
```

## Payment & Subscription Management

### Payments

#### List All Payments

```
GET /payments
```

**Query Parameters:**
- `user_id` - Filter by user ID
- `status` - Filter by status (pending, completed, failed, refunded)
- `payment_method` - Filter by payment method
- `from_date` - Filter by date range start
- `to_date` - Filter by date range end
- `search` - Search by transaction ID
- `per_page` - Items per page (default: 15)

#### Get a Specific Payment

```
GET /payments/{payment_id}
```

#### Create a Payment

```
POST /payments
```

**Request Body:**
```json
{
  "user_id": 1,
  "payment_method": "credit_card",
  "amount": 99.99,
  "currency": "USD",
  "status": "completed",
  "transaction_id": "txn_123456789",
  "payment_provider": "stripe",
  "payment_details": {
    "card_last4": "4242",
    "card_brand": "visa"
  },
  "item_type": "course",
  "item_id": 1,
  "item_name": "Advanced English Course"
}
```

#### Update a Payment

```
PUT /payments/{payment_id}
```

**Request Body:** Same as Create

#### Delete a Payment

```
DELETE /payments/{payment_id}
```

### Receipts

#### List All Receipts

```
GET /receipts
```

**Query Parameters:**
- `user_id` - Filter by user ID
- `from_date` - Filter by date range start
- `to_date` - Filter by date range end
- `search` - Search by receipt number or item name
- `item_type` - Filter by item type (course, subscription_plan)
- `per_page` - Items per page (default: 15)

#### Get a Specific Receipt

```
GET /receipts/{receipt_id}
```

#### Download a Receipt

```
GET /receipts/{receipt_id}/download
```

### Subscription Plans

#### List All Subscription Plans

```
GET /subscription-plans
```

**Query Parameters:**
- `is_active` - Filter by active status (true/false)
- `billing_cycle` - Filter by billing cycle (monthly, yearly, one-time)
- `search` - Search by name
- `per_page` - Items per page (default: 15)

#### Get a Specific Subscription Plan

```
GET /subscription-plans/{plan_id}
```

#### Create a Subscription Plan

```
POST /subscription-plans
```

**Request Body:**
```json
{
  "name": "Premium Monthly",
  "description": "Access to all premium content with monthly billing",
  "price": 19.99,
  "currency": "USD",
  "billing_cycle": "monthly",
  "duration_days": null,
  "is_active": true
}
```

#### Update a Subscription Plan

```
PUT /subscription-plans/{plan_id}
```

**Request Body:** Same as Create

#### Delete a Subscription Plan

```
DELETE /subscription-plans/{plan_id}
```

### User Subscriptions

#### List All User Subscriptions

```
GET /user-subscriptions
```

**Query Parameters:**
- `user_id` - Filter by user ID
- `subscription_plan_id` - Filter by subscription plan ID
- `status` - Filter by status (active, canceled, expired)
- `from_start_date` - Filter by start date range start
- `to_start_date` - Filter by start date range end
- `auto_renew` - Filter by auto-renew status (true/false)
- `per_page` - Items per page (default: 15)

#### Get a Specific User Subscription

```
GET /user-subscriptions/{subscription_id}
```

#### Create a User Subscription

```
POST /user-subscriptions
```

**Request Body:**
```json
{
  "user_id": 1,
  "subscription_plan_id": 2,
  "payment_id": 3,
  "starts_at": "2023-06-01T00:00:00Z",
  "ends_at": "2023-07-01T00:00:00Z",
  "status": "active",
  "auto_renew": true
}
```

#### Update a User Subscription

```
PUT /user-subscriptions/{subscription_id}
```

**Request Body:** Same as Create

#### Cancel a User Subscription

```
POST /user-subscriptions/{subscription_id}/cancel
```

**Request Body:**
```json
{
  "reason": "Found a better service"
}
```

#### Delete a User Subscription

```
DELETE /user-subscriptions/{subscription_id}
```

# Learner API Endpoints

## Courses

### List Available Courses

```
GET /courses
```

**Query Parameters:**
- `featured` - Filter by featured status (true/false)
- `sort_field` - Field to sort by (default: sort_order)
- `sort_direction` - Sort direction (asc/desc)
- `per_page` - Items per page (default: 15)

### Get a Specific Course

```
GET /courses/{course_id}
```

## Levels

### Get a Specific Level

```
GET /levels/{level_id}
```

## Lessons

### Get a Specific Lesson

```
GET /lessons/{lesson_id}
```

## Billing History

### List User Receipts

```
GET /receipts
```

**Query Parameters:**
- `from_date` - Filter by date range start
- `to_date` - Filter by date range end
- `item_type` - Filter by item type (course, subscription_plan)
- `per_page` - Items per page (default: 10)

### Get a Specific Receipt

```
GET /receipts/{receipt_id}
```

### Download a Receipt

```
GET /receipts/{receipt_id}/download
```

## Assessment System

### Exams

#### List Available Exams

```
GET /exams
```

**Query Parameters:**
- `course_id` - Filter by course ID
- `level_id` - Filter by level ID
- `lesson_id` - Filter by lesson ID
- `type` - Filter by exam type (lesson_quiz, level_end, course_end, placement)

#### Get a Specific Exam

```
GET /exams/{exam_id}
```

#### Start an Exam Attempt

```
POST /exams/{exam_id}/start
```

#### List Exam Attempts

```
GET /exams/{exam_id}/attempts
```

#### Get Placement Test

```
GET /placement-test?course_id={course_id}
```

### Exam Attempts

#### Get a Specific Attempt

```
GET /exam-attempts/{attempt_id}
```

#### Complete an Exam Attempt

```
POST /exam-attempts/{attempt_id}/complete
```

#### Submit a Response to a Question

```
POST /exam-attempts/{attempt_id}/questions/{question_id}
```

**Request Body:**
```json
{
  "user_answer": ["Paris"],
  "section_id": 1
}
```

## Terms

### List Terms for a Course

```
GET /courses/{course_id}/terms
```

**Query Parameters:**
- `term` - Search by term text
- `sort_field` - Field to sort by (default: term)
- `sort_direction` - Sort direction (asc/desc)
- `per_page` - Items per page (default: 15)

### Get a Specific Term

```
GET /terms/{term_id}
```

### Get Terms Due for Revision

```
GET /terms/due-revisions
```

**Query Parameters:**
- `course_id` - Filter by course ID (optional)
- `per_page` - Items per page (default: 15)

## Concepts

### List Concepts for a Course

```
GET /courses/{course_id}/concepts
```

**Query Parameters:**
- `type` - Filter by concept type
- `title` - Search by title
- `sort_field` - Field to sort by (default: title->en)
- `sort_direction` - Sort direction (asc/desc)
- `per_page` - Items per page (default: 15)

### Get a Specific Concept

```
GET /concepts/{concept_id}
```

### Get Concepts by Type

```
GET /courses/{course_id}/concepts/{type}
```

**Query Parameters:**
- `sort_field` - Field to sort by (default: title->en)
- `sort_direction` - Sort direction (asc/desc)

## Enrollments

### List User Enrollments

```
GET /enrollments
```

**Query Parameters:**
- `completed` - Filter by completion status (true/false)
- `sort_field` - Field to sort by (default: last_accessed_at)
- `sort_direction` - Sort direction (default: desc)

### Enroll in a Course

```
POST /courses/{course_id}/enroll
```

### Get Enrollment Details

```
GET /courses/{course_id}/enrollment
```

### Update Last Accessed Time

```
POST /courses/{course_id}/update-last-accessed
```

### Mark Course as Completed

```
POST /courses/{course_id}/mark-completed
```

## Progress Tracking

### Complete Lesson

Mark a lesson as complete and update progress for terms/concepts tested in the lesson.

```
POST /lessons/{lesson_id}/complete
```

**Request Body:**
```json
{
  "results": [
    {
      "slide_id": 1,
      "attempts": 2
    },
    {
      "slide_id": 2,
      "attempts": 1
    }
  ]
}
```

**Response:**
```json
{
  "message": "Lesson completed and progress updated",
  "updated_items_count": 2
}
```

**Grading Logic:**
- 1 attempt: Easy (Grade 4)
- 2 attempts: Good (Grade 3)
- 3 attempts: Hard (Grade 2)
- 4+ attempts: Again (Grade 1)

**Note:** If a Term or Concept is tested multiple times in a lesson (across different slides), the system uses the worst grade (highest attempt count) to update the revision item.

# Gamification API Endpoints

## User Trophies

### Get User Trophies

```
GET /api/gamification/trophies
```

**Query Parameters:**
- `sort_by` - Field to sort by (default: earned_at)
- `sort_direction` - Sort direction (default: desc)
- `per_page` - Items per page (default: 15)

### Get Available Trophies

```
GET /api/gamification/available-trophies
```

**Query Parameters:**
- `sort_by` - Field to sort by (default: rarity)
- `sort_direction` - Sort direction (default: asc)
- `per_page` - Items per page (default: 15)

### Get Trophy Statistics

```
GET /api/gamification/trophy-statistics
```

## User Points

### Get User Points

```
GET /api/gamification/points
```

**Query Parameters:**
- `from_date` - Filter by date range start
- `to_date` - Filter by date range end
- `category` - Filter by point category
- `per_page` - Items per page (default: 15)

## Leaderboards

### View Leaderboard

```
GET /api/gamification/leaderboards/{leaderboard}
```

**Query Parameters:**
- `limit` - Number of entries to return (default: 10)
- `page` - Page number for pagination

### Get User Leaderboard Rankings

```
GET /api/gamification/rankings
```

# Revision System API Endpoints

## Revision Items

### List Revision Items

**Endpoint:** `GET /api/revision/items`

**Query Parameters:**
- `state` (optional): Filter by state ('new', 'learning', 'review', 'relearning')
- `due` (optional): Filter by due status (true/false)
- `type` (optional): Filter by type ('term', 'concept')
- `course_id` (optional): Filter by course ID
- `limit` (optional): Number of items per page (default: 20)

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "revisionable_type": "App\\Models\\Term",
            "revisionable_id": 42,
            "difficulty": 4.93,
            "stability": 2.4,
            "interval": 4,
            "due_date": "2023-06-05T12:00:00.000000Z",
            "last_review": "2023-06-01T12:00:00.000000Z",
            "review_count": 1,
            "lapse_count": 0,
            "state": "learning",
            "retrievability": 0.98,
            "revisionable": {
                "id": 42,
                "term": "hello",
                "meaning": "a greeting",
                "translation": "hola",
                "media_url": null,
                "media_type": null
            }
        }
    ],
    "links": { "..." },
    "meta": { "..." }
}
```

### Get Due Items

**Endpoint:** `GET /api/revision/due-items`

**Query Parameters:**
- `course_id` (optional): Filter by course ID
- `limit` (optional): Number of items per page (default: 20)

**Response:** Same format as List Revision Items, but only includes items due for review.

### Add Item to Revision List

**Endpoint:** `POST /api/revision/add-item`

**Request:**
```json
{
    "type": "term",  // or "concept"
    "id": 42
}
```

**Response:**
```json
{
    "message": "Item added to revision list",
    "item": {
        "id": 1,
        "user_id": 1,
        "revisionable_type": "App\\Models\\Term",
        "revisionable_id": 42,
        "state": "new",
        "due_date": "2023-06-01T12:00:00.000000Z",
        "revisionable": {
            "id": 42,
            "term": "hello",
            "meaning": "a greeting",
            "translation": "hola"
        }
    }
}
```

### Record Response

**Endpoint:** `POST /api/revision/items/{revisionItem}/response`

**Request:**
```json
{
    "grade": 3,  // 1=Again, 2=Hard, 3=Good, 4=Easy
    "mastery_progress": [
        {
            "category": "pronunciation",
            "description": "Working on the 'th' sound",
            "strength": 3
        }
    ]
}
```

**Response:**
```json
{
    "message": "Response recorded successfully",
    "item": {
        "id": 1,
        "difficulty": 4.5,
        "stability": 5.2,
        "interval": 7,
        "due_date": "2023-06-08T12:00:00.000000Z",
        "last_review": "2023-06-01T12:00:00.000000Z",
        "review_count": 2,
        "lapse_count": 0,
        "state": "review",
        "retrievability": 0.98,
        "revisionable": { "..." }
    },
    "next_intervals": {
        "1": { "days": 1, "due_date": "2023-06-02T12:00:00.000000Z", "stability": 0.5, "difficulty": 5.2 },
        "2": { "days": 4, "due_date": "2023-06-05T12:00:00.000000Z", "stability": 3.1, "difficulty": 4.8 },
        "3": { "days": 7, "due_date": "2023-06-08T12:00:00.000000Z", "stability": 5.2, "difficulty": 4.5 },
        "4": { "days": 12, "due_date": "2023-06-13T12:00:00.000000Z", "stability": 9.1, "difficulty": 4.1 }
    }
}
```

### Get Mastery Progress

**Endpoint:** `GET /api/revision/mastery-progress`

**Query Parameters:**
- `course_id` (optional): Filter by course ID
- `category` (optional): Filter by category
- `strength_below` (optional): Filter by strength below a certain value

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "revision_item_id": 1,
            "category": "pronunciation",
            "description": "Working on the 'th' sound",
            "strength": 3,
            "last_identified_at": "2023-06-01T12:00:00.000000Z",
            "revision_item": {
                "id": 1,
                "revisionable": {
                    "id": 42,
                    "term": "hello",
                    "meaning": "a greeting"
                }
            }
        }
    ],
    "links": { "..." },
    "meta": { "..." }
}
```

### Generate Practice Questions

**Endpoint:** `GET /api/revision/practice`

**Query Parameters:**
- `course_id` (optional): Filter by course ID
- `count` (optional): Number of practice questions to generate (default: 5)
- `include_mastery_progress` (optional): Include items with mastery progress (default: true)
- `type` (optional): Filter by type ('term', 'concept', 'both')

**Response:**
```json
{
    "practice_questions": [
        {
            "revision_item_id": 1,
            "question_type": "meaning",
            "prompt": "What is the meaning of 'hello'?",
            "revisionable": {
                "id": 42,
                "term": "hello",
                "meaning": "a greeting",
                "translation": "hola",
                "media_url": null,
                "media_type": null
            }
        }
    ],
    "count": 1
}
```

### Get Revision Statistics

**Endpoint:** `GET /api/revision/statistics`

**Query Parameters:**
- `course_id` (optional): Filter by course ID

**Response:**
```json
{
    "total_items": 42,
    "state_counts": {
        "new": 5,
        "learning": 10,
        "review": 25,
        "relearning": 2
    },
    "due_count": 7,
    "reviews_by_day": {
        "2023-05-28": 15,
        "2023-05-29": 20,
        "2023-05-30": 18,
        "2023-05-31": 25,
        "2023-06-01": 22
    },
    "mastery_progress": {
        "total": 15,
        "by_category": {
            "pronunciation": 7,
            "meaning": 5,
            "usage": 3
        }
    }
}
```
