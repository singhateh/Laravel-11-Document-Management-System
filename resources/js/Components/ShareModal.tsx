import { useState } from 'react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import axios from 'axios';
import { router } from '@inertiajs/react';

interface ShareModalProps {
  show: boolean;
  onClose: () => void;
  documentId: number;
  documentName: string;
  slug?: string;
  onSuccess?: () => void;
}

interface PermissionLevel {
  value: string;
  label: string;
  description: string;
}

const permissionLevels: PermissionLevel[] = [
  {
    value: 'viewer',
    label: 'Viewer',
    description: 'Can view and download',
  },
  {
    value: 'commenter',
    label: 'Commenter',
    description: 'Can view, download, and add comments',
  },
  {
    value: 'editor',
    label: 'Editor',
    description: 'Can view, download, edit, and comment',
  },
  {
    value: 'co_owner',
    label: 'Co-owner',
    description: 'Can view, download, edit, comment, and share',
  },
];

export default function ShareModal({
  show,
  onClose,
  documentId,
  documentName,
  slug = 'document',
  onSuccess,
}: ShareModalProps) {
  const [permissionLevel, setPermissionLevel] = useState('viewer');
  const [expirationDate, setExpirationDate] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [shareLink, setShareLink] = useState('');
  const [showLink, setShowLink] = useState(false);

  const handleShare = async () => {
    setIsLoading(true);
    
    try {
      const response = await axios.post('/api/collaboration/shares', {
        shared_id: documentId,
        slug: slug,
        name: documentName,
        valid_until: expirationDate || null,
        visibility: isPublic ? 'public' : 'private',
        permission_level: permissionLevel,
      });

      const share = response.data.share;
      const link = `${window.location.origin}/shares/${slug}/${documentId}/${share.token}`;
      setShareLink(link);
      setShowLink(true);

      if (onSuccess) {
        onSuccess();
      }
    } catch (error) {
      console.error('Failed to share document:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(shareLink);
      alert('Link copied to clipboard!');
    } catch (error) {
      console.error('Failed to copy link:', error);
    }
  };



  const handleClose = () => {
    setShowLink(false);
    setShareLink('');
    setPermissionLevel('viewer');
    setExpirationDate('');
    setIsPublic(false);
    onClose();
  };

    return (
        <Modal show={show} onClose={handleClose} title={`Share ${slug === 'stego' ? 'Stego File' : slug === 'folder' ? 'Folder' : 'Document'}`}>
      <div className="space-y-6">
        {!showLink ? (
          <>
            <div>
              <h3 className="text-lg font-medium text-gray-900">
                {documentName}
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                Choose who can access this {slug === 'folder' ? 'folder' : slug === 'stego' ? 'stego file' : 'document'} and what they can do.
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-3">
                Permission Level
              </label>
              <div className="space-y-3">
                {permissionLevels.map((level) => (
                  <label
                    key={level.value}
                    className="flex items-start space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors"
                  >
                    <input
                      type="radio"
                      name="permissionLevel"
                      value={level.value}
                      checked={permissionLevel === level.value}
                      onChange={(e) => setPermissionLevel(e.target.value)}
                      className="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                    />
                    <div>
                      <div className="font-medium text-gray-900">{level.label}</div>
                      <div className="text-sm text-gray-500">{level.description}</div>
                    </div>
                  </label>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Expiration Date (Optional)
              </label>
              <input
                type="date"
                value={expirationDate}
                onChange={(e) => setExpirationDate(e.target.value)}
                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                min={new Date().toISOString().split('T')[0]}
              />
            </div>

            <div className="flex items-center">
              <input
                id="public"
                name="public"
                type="checkbox"
                checked={isPublic}
                onChange={(e) => setIsPublic(e.target.checked)}
                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              />
              <label htmlFor="public" className="ml-2 block text-sm text-gray-700">
                Make this share link public
              </label>
            </div>

            <div className="flex space-x-3">
              <PrimaryButton
                onClick={handleShare}
                disabled={isLoading}
                className="flex-1"
              >
                {isLoading ? 'Sharing...' : 'Create Share Link'}
              </PrimaryButton>
              <SecondaryButton onClick={handleClose}>
                Cancel
              </SecondaryButton>
            </div>
          </>
        ) : (
          <>
            <div className="text-center">
              <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg
                  className="w-6 h-6 text-green-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                {slug === 'stego' ? 'Stego File' : 'Document'} Shared Successfully!
              </h3>
              <p className="text-sm text-gray-500 mb-4">
                Share this link with people you want to collaborate with.
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-3">
              <div className="flex items-center space-x-2">
                <input
                  type="text"
                  value={shareLink}
                  readOnly
                  className="flex-1 bg-transparent border-none text-sm text-gray-700"
                />
                <button
                  onClick={handleCopyLink}
                  className="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                >
                  Copy
                </button>
              </div>
            </div>

            <div className="flex space-x-3">
              <PrimaryButton onClick={handleClose} className="flex-1">
                Done
              </PrimaryButton>
            </div>
          </>
        )}
      </div>
    </Modal>
  );
}
