import { usePage } from '@inertiajs/react';
import React from 'react';

export default function HODReviewPage(): React.ReactElement {
    const { workOrder, employees } = usePage().props;

    return (
        <div className="layout">
            <h1>HOD Review — {workOrder?.nomor_wo}</h1>
            <p>HOD review page for Work Order.</p>
            {employees && (
                <ul>
                    {employees.map((e: { id: string; nama_employee: string }) => (
                        <li key={e.id}>{e.nama_employee}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
