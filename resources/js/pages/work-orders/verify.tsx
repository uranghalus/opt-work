import { usePage } from '@inertiajs/react';
import React from 'react';

export default function VerifyPage(): React.ReactElement {
    const { workOrder } = usePage().props;

    return (
        <div className="layout">
            <h1>Verify — {workOrder?.nomor_wo}</h1>
            <p>Verify page for Work Order.</p>
        </div>
    );
}
