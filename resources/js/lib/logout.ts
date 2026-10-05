import { slo } from '@/routes/saml';

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

export function submitLogout(): void {
    const { action, method } = slo.form();

    const form = document.createElement('form');
    form.method = method;
    form.action = action;

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrfToken();
    form.append(token);

    document.body.append(form);
    form.submit();
}
