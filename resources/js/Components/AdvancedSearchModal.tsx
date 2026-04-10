import { useState, FormEventHandler } from 'react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';

interface Tag {
    id: number;
    name: string;
}

interface Folder {
    id: number;
    name: string;
}

interface SearchModalProps {
    show: boolean;
    onClose: () => void;
    tags?: Tag[];
    folders?: Folder[];
}

export default function AdvancedSearchModal({ 
    show, 
    onClose, 
    tags = [], 
    folders = [] 
}: SearchModalProps) {
    const [searchData, setSearchData] = useState({
        keyword: '',
        fileType: '',
        folderId: '',
        tagIds: [] as number[],
        dateFrom: '',
        dateTo: '',
        minSize: '',
        maxSize: '',
        visibility: '',
    });

    const fileTypes = [
        { value: '', label: 'All Types' },
        { value: 'pdf', label: 'PDF' },
        { value: 'doc,docx', label: 'Word Documents' },
        { value: 'xls,xlsx', label: 'Excel Spreadsheets' },
        { value: 'ppt,pptx', label: 'PowerPoint' },
        { value: 'jpg,jpeg,png,gif', label: 'Images' },
        { value: 'mp4,avi,mov,mkv', label: 'Videos' },
        { value: 'mp3,wav', label: 'Audio' },
    ];

    const handleSearch: FormEventHandler = (e) => {
        e.preventDefault();

        // Build query parameters
        const params = new URLSearchParams();
        
        if (searchData.keyword) params.append('q', searchData.keyword);
        if (searchData.fileType) params.append('type', searchData.fileType);
        if (searchData.folderId) params.append('folder', searchData.folderId);
        if (searchData.tagIds.length > 0) params.append('tags', searchData.tagIds.join(','));
        if (searchData.dateFrom) params.append('from', searchData.dateFrom);
        if (searchData.dateTo) params.append('to', searchData.dateTo);
        if (searchData.minSize) params.append('min_size', searchData.minSize);
        if (searchData.maxSize) params.append('max_size', searchData.maxSize);
        if (searchData.visibility) params.append('visibility', searchData.visibility);

        router.get(`/search?${params.toString()}`);
        onClose();
    };

    const handleReset = () => {
        setSearchData({
            keyword: '',
            fileType: '',
            folderId: '',
            tagIds: [],
            dateFrom: '',
            dateTo: '',
            minSize: '',
            maxSize: '',
            visibility: '',
        });
    };

    const toggleTag = (tagId: number) => {
        setSearchData(prev => ({
            ...prev,
            tagIds: prev.tagIds.includes(tagId)
                ? prev.tagIds.filter(id => id !== tagId)
                : [...prev.tagIds, tagId]
        }));
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="3xl">
            <form onSubmit={handleSearch} className="p-6">
                <h2 className="text-lg font-medium text-gray-900">Advanced Search</h2>
                <p className="mt-1 text-sm text-gray-600">
                    Search documents with multiple filters
                </p>

                <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {/* Keyword Search */}
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="keyword" value="Keyword" />
                        <TextInput
                            id="keyword"
                            type="text"
                            value={searchData.keyword}
                            onChange={(e) => setSearchData({ ...searchData, keyword: e.target.value })}
                            className="mt-1 block w-full"
                            placeholder="Search by name or content..."
                        />
                    </div>

                    {/* File Type */}
                    <div>
                        <InputLabel htmlFor="fileType" value="File Type" />
                        <select
                            id="fileType"
                            value={searchData.fileType}
                            onChange={(e) => setSearchData({ ...searchData, fileType: e.target.value })}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {fileTypes.map(type => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Folder */}
                    <div>
                        <InputLabel htmlFor="folder" value="Folder" />
                        <select
                            id="folder"
                            value={searchData.folderId}
                            onChange={(e) => setSearchData({ ...searchData, folderId: e.target.value })}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Folders</option>
                            {folders.map(folder => (
                                <option key={folder.id} value={folder.id}>
                                    {folder.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Date Range */}
                    <div>
                        <InputLabel htmlFor="dateFrom" value="Date From" />
                        <TextInput
                            id="dateFrom"
                            type="date"
                            value={searchData.dateFrom}
                            onChange={(e) => setSearchData({ ...searchData, dateFrom: e.target.value })}
                            className="mt-1 block w-full"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="dateTo" value="Date To" />
                        <TextInput
                            id="dateTo"
                            type="date"
                            value={searchData.dateTo}
                            onChange={(e) => setSearchData({ ...searchData, dateTo: e.target.value })}
                            className="mt-1 block w-full"
                        />
                    </div>

                    {/* File Size Range */}
                    <div>
                        <InputLabel htmlFor="minSize" value="Min Size (KB)" />
                        <TextInput
                            id="minSize"
                            type="number"
                            value={searchData.minSize}
                            onChange={(e) => setSearchData({ ...searchData, minSize: e.target.value })}
                            className="mt-1 block w-full"
                            placeholder="e.g., 100"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="maxSize" value="Max Size (KB)" />
                        <TextInput
                            id="maxSize"
                            type="number"
                            value={searchData.maxSize}
                            onChange={(e) => setSearchData({ ...searchData, maxSize: e.target.value })}
                            className="mt-1 block w-full"
                            placeholder="e.g., 10000"
                        />
                    </div>

                    {/* Visibility */}
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="visibility" value="Visibility" />
                        <select
                            id="visibility"
                            value={searchData.visibility}
                            onChange={(e) => setSearchData({ ...searchData, visibility: e.target.value })}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Documents</option>
                            <option value="public">Public Only</option>
                            <option value="private">Private Only</option>
                        </select>
                    </div>

                    {/* Tags */}
                    {tags.length > 0 && (
                        <div className="sm:col-span-2">
                            <InputLabel value="Tags" />
                            <div className="mt-2 flex flex-wrap gap-2">
                                {tags.map(tag => (
                                    <button
                                        key={tag.id}
                                        type="button"
                                        onClick={() => toggleTag(tag.id)}
                                        className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${
                                            searchData.tagIds.includes(tag.id)
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                        }`}
                                    >
                                        {tag.name}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <div className="mt-6 flex justify-between">
                    <SecondaryButton type="button" onClick={handleReset}>
                        Reset
                    </SecondaryButton>
                    <div className="flex gap-3">
                        <SecondaryButton type="button" onClick={onClose}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit">
                            Search
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
