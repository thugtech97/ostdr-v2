import ThemeToggle from '@/Components/ThemeToggle';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen bg-white dark:bg-gray-950">
            <ThemeToggle className="absolute right-5 top-5 z-10" />

            <div className="hidden w-1/2 flex-col items-center justify-center border-r border-brand-100 bg-brand-50 p-12 lg:flex dark:border-gray-800 dark:bg-gray-900">
                {/* The logo has navy artwork, so it sits on a light card in dark mode. */}
                <div className="w-full max-w-md dark:rounded-3xl dark:bg-white dark:p-8">
                    <img
                        src="/images/logo.svg"
                        alt="Philsaga Mining Corporation"
                        className="w-full"
                    />
                </div>

                <div className="mt-10 text-center">
                    <p className="text-sm font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">
                        Philsaga Mining Corporation
                    </p>
                    <p className="mt-2 text-2xl font-semibold text-brand-900 dark:text-white">
                        Online Stock Transfer Request System
                    </p>
                </div>
            </div>

            <div className="flex w-full flex-col justify-center px-4 py-12 sm:px-12 lg:w-1/2">
                <div className="mx-auto w-full max-w-md">
                    <div className="mb-8 flex items-center gap-4 lg:hidden">
                        <div className="shrink-0 dark:rounded-xl dark:bg-white dark:p-2">
                            <img
                                src="/images/logo.svg"
                                alt="Philsaga Mining Corporation"
                                className="h-16 w-auto"
                            />
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-300">
                                Philsaga Mining Corporation
                            </p>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Online Stock Transfer Request System
                            </p>
                        </div>
                    </div>

                    {children}

                    <div className="mt-12 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                            OSTR v2 &middot; &copy; {new Date().getFullYear()}{' '}
                            Philsaga Mining Corporation
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
