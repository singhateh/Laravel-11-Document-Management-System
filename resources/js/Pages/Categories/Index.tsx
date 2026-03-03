import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState, FormEventHandler } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import DangerButton from '@/Components/DangerButton';

interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string;
    created_at: string;
}

interface CategoriesPageProps extends PageProps {
    categories: Category[];
}

export default function Index({ auth, categories }: CategoriesPageProps) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Category | null>(null);
    const [formData, setFormData] = useState({ name: '', description: '' });
    const [processing, setProcessing] = useState(false);

    const handleCreate: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);

        router.post('/categories', formData, {
            onSuccess: () => {
                setShowCreateModal(false);
                setFormData({ name: '', description: '' });
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleEdit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editingCategory) return;

        setProcessing(true);

        router.put(`/categories/${editingCategory.id}`, formData, {
            onSuccess: () => {
                setShowEditModal(false);
                setEditingCategory(null);
                setFormData({ name: '', description: '' });
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleDelete = (id: number) => {
        if (!confirm('Are you sure you want to delete this category?')) return;

        router.delete(`/categories/${id}`);
    };

    const openEditModal = (category: Category) => {
        setEditingCategory(category);
        setFormData({ name: category.name, description: category.description || '' });
        setShowEditModal(true);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Categories
                    </h2>
                    <PrimaryButton onClick={() => setShowCreateModal(true)}>
                        Create Category
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Categories" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {categories.length === 0 ? (
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
                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">
                                        No categories
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Get started by creating a new category.
                                    </p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {categories.map((category) => (
                                        <div
                                            key={category.id}
                                            className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <h3 className="text-lg font-semibold text-gray-900">
                                                        {category.name}
                                                    </h3>
                                                    {category.description && (
                                                        <p className="mt-1 text-sm text-gray-600">
                                                            {category.description}
                                                        </p>
                                                    )}
                                                    <p className="mt-2 text-xs text-gray-500">
                                                        {new Date(category.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="mt-4 flex gap-2">
                                                <SecondaryButton
                                                    onClick={() => openEditModal(category)}
                                                >
                                                    Edit
                                                </SecondaryButton>
                                                <DangerButton
                                                    onClick={() => handleDelete(category.id)}
                                                >
                                                    Delete
                                                </DangerButton>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Create Modal */}
            <Modal show={showCreateModal} onClose={() => setShowCreateModal(false)}>
                <form onSubmit={handleCreate} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">Create Category</h2>

                    <div className="mt-6">
                        <InputLabel htmlFor="name" value="Category Name" />
                        <TextInput
                            id="name"
                            type="text"
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            className="mt-1 block w-full"
                            required
                        />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="description" value="Description (Optional)" />
                        <textarea
                            id="description"
                            value={formData.description}
                            onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={3}
                        />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setShowCreateModal(false)} disabled={processing}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={processing}>Create</PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit Modal */}
            <Modal show={showEditModal} onClose={() => setShowEditModal(false)}>
                <form onSubmit={handleEdit} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">Edit Category</h2>

                    <div className="mt-6">
                        <InputLabel htmlFor="edit_name" value="Category Name" />
                        <TextInput
                            id="edit_name"
                            type="text"
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            className="mt-1 block w-full"
                            required
                        />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="edit_description" value="Description (Optional)" />
                        <textarea
                            id="edit_description"
                            value={formData.description}
                            onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={3}
                        />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setShowEditModal(false)} disabled={processing}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={processing}>Update</PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
