import * as React from 'react';

import { Toast } from './toast';

const TOAST_LIMIT = 4;
const TOAST_REMOVE_DELAY = 5000;

type ToastOptions = Omit<React.ComponentPropsWithoutRef<typeof Toast>, 'id'> & {
    title?: React.ReactNode;
    description?: React.ReactNode;
};

type ToasterToast = ToastOptions & {
    id: string;
};

type State = {
    toasts: ToasterToast[];
};

type Action =
    | { type: 'ADD_TOAST'; toast: ToasterToast }
    | { type: 'DISMISS_TOAST'; toastId?: string }
    | { type: 'REMOVE_TOAST'; toastId?: string };

let count = 0;

const generateId = () => {
    count = (count + 1) % Number.MAX_SAFE_INTEGER;
    return count.toString();
};

const reducer = (state: State, action: Action): State => {
    switch (action.type) {
        case 'ADD_TOAST':
            return { toasts: [action.toast, ...state.toasts].slice(0, TOAST_LIMIT) };
        case 'DISMISS_TOAST':
            return {
                toasts: state.toasts.map((toast) =>
                    action.toastId === undefined || action.toastId === toast.id ? { ...toast, open: false } : toast,
                ),
            };
        case 'REMOVE_TOAST':
            if (action.toastId === undefined) return { toasts: [] };
            return { toasts: state.toasts.filter((toast) => toast.id !== action.toastId) };
    }
};

let memoryState: State = { toasts: [] };
const listeners: Array<(state: State) => void> = [];
const toastTimeouts = new Map<string, ReturnType<typeof setTimeout>>();

const dispatch = (action: Action) => {
    memoryState = reducer(memoryState, action);
    listeners.forEach((listener) => listener(memoryState));
};

const addToRemoveQueue = (toastId: string, duration = TOAST_REMOVE_DELAY) => {
    if (toastTimeouts.has(toastId)) return;

    const timeout = setTimeout(() => {
        toastTimeouts.delete(toastId);
        dispatch({ type: 'REMOVE_TOAST', toastId });
    }, duration + 250);

    toastTimeouts.set(toastId, timeout);
};

const dismiss = (toastId?: string) => {
    dispatch({ type: 'DISMISS_TOAST', toastId });
    if (toastId === undefined) {
        memoryState.toasts.forEach((toast) => addToRemoveQueue(toast.id, toast.duration));
    } else {
        const activeToast = memoryState.toasts.find((toast) => toast.id === toastId);
        addToRemoveQueue(toastId, activeToast?.duration);
    }
};

const toast = ({ ...props }: ToastOptions) => {
    const id = generateId();
    dispatch({
        type: 'ADD_TOAST',
        toast: { ...props, id, open: true },
    });

    return {
        id,
        dismiss: () => dismiss(id),
    };
};

const useToast = () => {
    const [state, setState] = React.useState<State>(memoryState);

    React.useEffect(() => {
        listeners.push(setState);

        return () => {
            const index = listeners.indexOf(setState);
            if (index >= 0) listeners.splice(index, 1);
        };
    }, []);

    return {
        ...state,
        toast,
        dismiss,
    };
};

export { toast, useToast };
