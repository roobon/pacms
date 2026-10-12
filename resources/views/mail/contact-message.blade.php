{{ $contact->name }} <{{ $contact->email }}> wrote through the contact form{{ $contact->page_url ? ' on '.$contact->page_url : '' }}:

{{ $contact->subject ? 'Subject: '.$contact->subject."\n\n" : '' }}{{ $contact->message }}

---
Reply to this e-mail to answer {{ $contact->name }}. The message is also in the admin (Messages) for {{ \App\Models\ContactMessage::RETENTION_DAYS }} days.
