import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';

interface Category {
    id: number;
    name: string;
}

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
    categories?: Category[];
    subfolders?: Folder[];
}

interface FoldersCreatePageProps extends PageProps {
    folders: Folder[];
}

export default function Create({ auth, folders }: FoldersCreatePageProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Folder
                </h2>
            }
        >
            <Head title="Create Folder" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="mb-4 text-lg font-medium">
                                Folder Structure
                            </h3>

                            {folders.length === 0 ? (
                                <div className="py-12 text-center">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">
                                        No folders
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Get started by creating a new folder.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {folders.map((folder) => (
                                        <div
                                            key={folder.id}
                                            className="rounded-lg border border-gray-200 p-4"
                                        >
                                            <div className="flex items-center">
                                                <svg
                                                    className="mr-3 h-6 w-6 text-yellow-500"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                                </svg>
                                                <span className="font-medium text-gray-900">
                                                    {folder.name}
                                                </span>
                                            </div>
                                            {folder.categories &&
                                                folder.categories.length >
                                                    0 && (
                                                    <div className="ml-9 mt-2 flex flex-wrap gap-2">
                                                        {folder.categories.map(
                                                            (category) => (
                                                                <span
                                                                    key={
                                                                        category.id
                                                                    }
                                                                    className="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-sm font-medium text-indigo-800"
                                                                >
                                                                    {
                                                                        category.name
                                                                    }
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            {folder.subfolders &&
                                                folder.subfolders.length >
                                                    0 && (
                                                    <div className="ml-9 mt-2 space-y-2">
                                                        {folder.subfolders.map(
                                                            (subfolder) => (
                                                                <div
                                                                    key={
                                                                        subfolder.id
                                                                    }
                                                                    className="flex items-center text-sm text-gray-600"
                                                                >
                                                                    <svg
                                                                        className="mr-2 h-4 w-4 text-gray-400"
                                                                        fill="currentColor"
                                                                        viewBox="0 0 20 20"
                                                                    >
                                                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                                                    </svg>
                                                                    {
                                                                        subfolder.name
                                                                    }
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
