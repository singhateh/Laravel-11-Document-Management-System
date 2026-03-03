import { FormEventHandler, useState } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import axios from 'axios';

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
}

interface DragDropUploadModalProps {
    show: boolean;
    onClose: () => void;
    folders: Folder[];
    currentFolderId?: number;
    onSuccess?: () => void;
}

export default function DragDropUploadModal({ 
    show, 
    onClose, 
    folders = [], 
    currentFolderId, 
    onSuccess 
}: DragDropUploadModalProps) {
    const [files, setFiles] = useState<FileList | null>(null);
    const [folderId, setFolderId] = useState(currentFolderId || '');
    const [visibility, setVisibility] = useState<'public' | 'private'>('public');
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState('');
    const [isDragging, setIsDragging] = useState(false);
    
    const folderList = Array.isArray(folders) ? folders : [];

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        // Only set to false if we're leaving the dropzone itself
        if (e.currentTarget === e.target) {
            setIsDragging(false);
        }
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const droppedFiles = e.dataTransfer.files;
        if (droppedFiles && droppedFiles.length > 0) {
            setFiles(droppedFiles);
        }
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            setFiles(e.target.files);
        }
    };

    const handleSubmit: FormEventHandler = async (e) => {
        e.preventDefault();
        setError('');

        if (!files || files.length === 0) {
            setError('Please select at least one file');
            return;
        }

        setUploading(true);

        const formData = new FormData();
        
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        
        if (folderId) {
            formData.append('folder_id', folderId.toString());
        }
        
        formData.append('visibility', visibility);

        try {
            await axios.post('/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            setFiles(null);
            setError('');
            
            if (onSuccess) {
                onSuccess();
            }
            
            onClose();
        } catch (err: any) {
            if (err.response?.data?.errors) {
                const errors = Object.values(err.response.data.errors).flat();
                setError(errors.join(', '));
            } else {
                setError(err.response?.data?.message || 'Upload failed');
            }
        } finally {
            setUploading(false);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <form onSubmit={handleSubmit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900">Upload Files</h2>

                <p className="mt-1 text-sm text-gray-600">
                    Drag and drop files or click to browse
                </p>

                {error && (
                    <div className="mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        {error}
                    </div>
                )}

                {/* Drag and Drop Zone */}
                <div
                    className={`mt-6 border-2 border-dashed rounded-lg p-8 text-center transition-colors ${
                        isDragging
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-300 bg-gray-50 hover:border-gray-400'
                    }`}
                    onDragEnter={handleDragEnter}
                    onDragOver={handleDragOver}
                    onDragLeave={handleDragLeave}
                    onDrop={handleDrop}
                >
                    <svg
                        className="mx-auto h-12 w-12 text-gray-400"
                        stroke="currentColor"
                        fill="none"
                        viewBox="0 0 48 48"
                    >
                        <path
                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                    <div className="mt-4">
                        <label
                            htmlFor="file-upload"
                            className="cursor-pointer font-medium text-indigo-600 hover:text-indigo-500"
                        >
                            Click to upload
                        </label>
                        <input
                            id="file-upload"
                            type="file"
                            multiple
                            onChange={handleFileSelect}
                            className="sr-only"
                        />
                        <p className="mt-1 text-sm text-gray-600">or drag and drop</p>
                    </div>
                    <p className="mt-2 text-xs text-gray-500">
                        Any file type, any size
                    </p>
                </div>

                {files && files.length > 0 && (
                    <div className="mt-4 p-4 bg-white border border-gray-200 rounded-lg">
                        <p className="text-sm font-medium text-gray-700 mb-3">
                            Selected {files.length} file{files.length !== 1 ? 's' : ''}:
                        </p>
                        <ul className="text-xs text-gray-600 space-y-2 max-h-40 overflow-y-auto">
                            {Array.from(files).map((file, index) => (
                                <li key={index} className="flex items-center justify-between py-2 px-3 bg-gray-50 rounded">
                                    <span className="truncate flex-1">{file.name}</span>
                                    <span className="ml-2 text-gray-500">
                                        {(file.size / 1024).toFixed(1)} KB
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="mt-6">
                    <InputLabel htmlFor="parent_folder" value="Parent Folder (Optional)" />
                    <select
                        id="parent_folder"
                        value={folderId}
                        onChange={(e) => setFolderId(e.target.value)}
                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    >
                        <option value="">Root / No Parent</option>
                        {folderList.map((folder) => (
                            <option key={folder.id} value={folder.id}>
                                {folder.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mt-4">
                    <label className="flex items-center">
                        <input
                            type="checkbox"
                            checked={visibility === 'private'}
                            onChange={(e) => setVisibility(e.target.checked ? 'private' : 'public')}
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        <span className="ms-2 text-sm text-gray-600">Make private</span>
                    </label>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose} disabled={uploading}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={uploading || !files || files.length === 0}>
                        {uploading ? 'Uploading...' : 'Upload'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
