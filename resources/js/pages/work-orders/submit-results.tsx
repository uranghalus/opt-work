import { usePage } from '@inertiajs/react';
import React from 'react';

export default function SubmitResultsPage(): React.ReactElement {
    const { workOrder } = usePage().props;

    return (
        <div className="layout">
            <h1>Submit Results — {workOrder?.nomor_wo}</h1>
            <p>Submit results page for Work Order.</p>
        </div>
    );
}
