/** Indonesian time-of-day greeting for the shell heading band. */
export function timeGreeting(date: Date = new Date()): string {
    const hour = date.getHours();

    if (hour < 11) {
        return 'Selamat pagi';
    }

    if (hour < 15) {
        return 'Selamat siang';
    }

    return 'Selamat sore';
}

/** First name only — greetings stay short in the section heading. */
export function greetingName(name?: string | null): string {
    if (!name) {
        return '';
    }

    return name.trim().split(/\s+/)[0] ?? '';
}
