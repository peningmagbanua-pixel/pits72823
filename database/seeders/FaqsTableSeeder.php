<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (Faq::exists()) {
            return;
        }

        $faqs = [
            [
                'question' => 'What are the requirements for enrollment?',
                'answer' => 'The general requirements include: (1) PSA Birth Certificate, (2) Grade 10 or 12 Report Card (SF9), (3) Certificate of Good Moral Character, (4) 2x2 ID pictures, and (5) Completed Application Form. Additional requirements may vary depending on the program you are applying for.',
                'sort_order' => 1,
            ],
            [
                'question' => 'How do I submit my application form?',
                'answer' => 'After completing the online application form through this portal, you need to print the form and submit it along with your documentary requirements to the Registrar\'s Office during office hours (Monday to Friday, 8:00 AM to 5:00 PM).',
                'sort_order' => 2,
            ],
            [
                'question' => 'When is the deadline for application?',
                'answer' => 'The application deadline varies per school year. Please check the Announcements section on your Dashboard or contact the Registrar\'s Office for the specific deadline dates for the current academic year.',
                'sort_order' => 3,
            ],
            [
                'question' => 'How do I know if my application is approved?',
                'answer' => 'You can check your application status anytime through this portal by navigating to Application > Application Status in the sidebar menu. You will also receive an email notification once your application has been processed.',
                'sort_order' => 4,
            ],
            [
                'question' => 'What happens after I submit my application?',
                'answer' => 'After submission, your application will be reviewed by the admissions committee. If qualified, you will be scheduled for an entrance examination. The exam schedule will be posted on your Dashboard, and you will also be notified via email.',
                'sort_order' => 5,
            ],
            [
                'question' => 'Can I update my application after submission?',
                'answer' => 'Once submitted, your application is locked for review. If you need to make changes, please contact the Registrar\'s Office directly or send an email to the admissions team for assistance.',
                'sort_order' => 6,
            ],
            [
                'question' => 'Is there an entrance examination?',
                'answer' => 'Yes, an entrance examination is required for all applicants. The exam covers Mathematics, English, Science, and General Information. You will be notified of your schedule through this portal once the admin assigns it.',
                'sort_order' => 7,
            ],
            [
                'question' => 'How do I reset my password?',
                'answer' => 'You can change your password by going to the Profile page and clicking on the "Security & Password" section. Enter your current password and your new password to update it.',
                'sort_order' => 8,
            ],
            [
                'question' => 'Who can I contact for support?',
                'answer' => 'For any inquiries or concerns, you may visit the Registrar\'s Office at Carranglan National High School during office hours, or contact us via email at cnhs.admissions@deped.gov.ph or call us at (044) 123-4567.',
                'sort_order' => 9,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}
