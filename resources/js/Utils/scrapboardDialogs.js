import Swal from 'sweetalert2';

const baseOptions = {
    buttonsStyling: false,
    reverseButtons: true,
    width: 620,
    padding: '2rem',
    customClass: {
        popup: 'box-border !w-[620px] !max-w-[620px] rounded-2xl border border-slate-200 text-slate-900 shadow-2xl',
        title: 'mx-auto w-full max-w-full px-8 text-xl font-semibold text-slate-900',
        htmlContainer: 'box-border !mx-0 !w-full max-w-full !px-8 overflow-hidden text-sm text-slate-500',
        inputLabel: 'mb-2 block text-sm font-medium text-slate-700',
        input: 'box-border !mx-0 mt-2 block !w-full !max-w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
        confirmButton: 'inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700',
        cancelButton: 'inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50',
        actions: 'mt-5 flex flex-wrap justify-center gap-2 px-8',
    },
};

export async function promptForName({
    title,
    inputLabel,
    inputValue = '',
    placeholder = '',
    confirmButtonText = 'Save',
}) {
    const result = await Swal.fire({
        ...baseOptions,
        title,
        input: 'text',
        inputLabel,
        inputValue,
        inputPlaceholder: placeholder,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        preConfirm: (value) => {
            const trimmed = String(value ?? '').trim();

            if (trimmed === '') {
                Swal.showValidationMessage('Please enter a name.');
                return false;
            }

            return trimmed;
        },
    });

    return result.isConfirmed ? result.value : null;
}

export async function confirmDangerAction({
    title,
    text,
    confirmButtonText = 'Delete',
}) {
    const result = await Swal.fire({
        ...baseOptions,
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancel',
        customClass: {
            ...baseOptions.customClass,
            confirmButton: 'inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700',
        },
    });

    return result.isConfirmed;
}
