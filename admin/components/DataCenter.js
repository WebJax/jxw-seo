import { useState, useEffect, useMemo } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    createColumnHelper,
} from '@tanstack/react-table';

const columnHelper = createColumnHelper();

const DataCenter = () => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [editingCell, setEditingCell] = useState(null);
    const [generatingAI, setGeneratingAI] = useState(new Set());

    // Fetch data from API
    const fetchData = async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: '/localseo/v1/data',
            });
            setData(response);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, []);

    // Update cell value
    const updateCell = async (rowId, columnId, value) => {
        try {
            await apiFetch({
                path: `/localseo/v1/data/${rowId}`,
                method: 'PUT',
                data: { [columnId]: value },
            });
            
            // Update local state
            setData(prevData =>
                prevData.map(row =>
                    row.id === rowId ? { ...row, [columnId]: value } : row
                )
            );
        } catch (err) {
            setError(err.message);
        }
    };

    // Generate AI content for a row
    const generateAI = async (rowId) => {
        setGeneratingAI(prev => new Set(prev).add(rowId));
        
        try {
            const response = await apiFetch({
                path: `/localseo/v1/generate-ai/${rowId}`,
                method: 'POST',
            });
            
            // Update local state with AI-generated content
            setData(prevData =>
                prevData.map(row =>
                    row.id === rowId ? response : row
                )
            );
        } catch (err) {
            setError(err.message);
        } finally {
            setGeneratingAI(prev => {
                const next = new Set(prev);
                next.delete(rowId);
                return next;
            });
        }
    };

    // Add new row
    const addRow = async () => {
        try {
            const response = await apiFetch({
                path: '/localseo/v1/data',
                method: 'POST',
                data: {
                    city: '',
                    zip: '',
                    service_keyword: '',
                    custom_slug: '',
                    ai_generated_intro: '',
                    meta_title: '',
                    meta_description: '',
                },
            });
            
            setData(prevData => [response, ...prevData]);
        } catch (err) {
            setError(err.message);
        }
    };

    // Delete row
    const deleteRow = async (rowId) => {
        if (!confirm(__('Are you sure you want to delete this row?', 'localseo-booster'))) {
            return;
        }

        try {
            await apiFetch({
                path: `/localseo/v1/data/${rowId}`,
                method: 'DELETE',
            });
            
            setData(prevData => prevData.filter(row => row.id !== rowId));
        } catch (err) {
            setError(err.message);
        }
    };

    // Bulk generate AI
    const bulkGenerateAI = async () => {
        setLoading(true);
        
        try {
            const response = await apiFetch({
                path: '/localseo/v1/generate-ai-bulk',
                method: 'POST',
            });
            
            alert(__(`Generated ${response.success} items. Failed: ${response.failed}`, 'localseo-booster'));
            fetchData(); // Refresh all data
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    // Define columns
    const columns = useMemo(() => [
        columnHelper.accessor('id', {
            header: 'ID',
            cell: info => info.getValue(),
            size: 60,
        }),
        columnHelper.accessor('city', {
            header: __('City', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                const isEditing = editingCell === `${rowId}-city`;

                return isEditing ? (
                    <input
                        type="text"
                        value={value}
                        onChange={e => {
                            const newValue = e.target.value;
                            setData(prevData =>
                                prevData.map(row =>
                                    row.id === rowId ? { ...row, city: newValue } : row
                                )
                            );
                        }}
                        onBlur={() => {
                            setEditingCell(null);
                            updateCell(rowId, 'city', value);
                        }}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                setEditingCell(null);
                                updateCell(rowId, 'city', value);
                            }
                        }}
                        autoFocus
                    />
                ) : (
                    <div onClick={() => setEditingCell(`${rowId}-city`)}>
                        {value || <em>{__('Click to edit', 'localseo-booster')}</em>}
                    </div>
                );
            },
            size: 120,
        }),
        columnHelper.accessor('zip', {
            header: __('ZIP', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                const isEditing = editingCell === `${rowId}-zip`;

                return isEditing ? (
                    <input
                        type="text"
                        value={value}
                        onChange={e => {
                            const newValue = e.target.value;
                            setData(prevData =>
                                prevData.map(row =>
                                    row.id === rowId ? { ...row, zip: newValue } : row
                                )
                            );
                        }}
                        onBlur={() => {
                            setEditingCell(null);
                            updateCell(rowId, 'zip', value);
                        }}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                setEditingCell(null);
                                updateCell(rowId, 'zip', value);
                            }
                        }}
                        autoFocus
                    />
                ) : (
                    <div onClick={() => setEditingCell(`${rowId}-zip`)}>
                        {value || <em>{__('Click to edit', 'localseo-booster')}</em>}
                    </div>
                );
            },
            size: 80,
        }),
        columnHelper.accessor('service_keyword', {
            header: __('Service', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                const isEditing = editingCell === `${rowId}-service_keyword`;

                return isEditing ? (
                    <input
                        type="text"
                        value={value}
                        onChange={e => {
                            const newValue = e.target.value;
                            setData(prevData =>
                                prevData.map(row =>
                                    row.id === rowId ? { ...row, service_keyword: newValue } : row
                                )
                            );
                        }}
                        onBlur={() => {
                            setEditingCell(null);
                            updateCell(rowId, 'service_keyword', value);
                        }}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                setEditingCell(null);
                                updateCell(rowId, 'service_keyword', value);
                            }
                        }}
                        autoFocus
                    />
                ) : (
                    <div onClick={() => setEditingCell(`${rowId}-service_keyword`)}>
                        {value || <em>{__('Click to edit', 'localseo-booster')}</em>}
                    </div>
                );
            },
            size: 150,
        }),
        columnHelper.accessor('custom_slug', {
            header: __('Slug', 'localseo-booster'),
            cell: info => {
                const value = info.getValue();
                return value ? (
                    <a href={`/localseo/${value}`} target="_blank" rel="noopener noreferrer">
                        {value}
                    </a>
                ) : (
                    <em>{__('Auto-generated', 'localseo-booster')}</em>
                );
            },
            size: 150,
        }),
        columnHelper.accessor('ai_generated_intro', {
            header: __('AI Intro', 'localseo-booster'),
            cell: info => {
                const value = info.getValue();
                return value ? (
                    <div className="truncate" title={value}>
                        {value.substring(0, 50)}...
                    </div>
                ) : (
                    <em>{__('Not generated', 'localseo-booster')}</em>
                );
            },
            size: 200,
        }),
        columnHelper.accessor('meta_title', {
            header: __('Meta Title', 'localseo-booster'),
            cell: info => {
                const value = info.getValue();
                return value ? (
                    <div className="truncate" title={value}>
                        {value}
                    </div>
                ) : (
                    <em>{__('Not set', 'localseo-booster')}</em>
                );
            },
            size: 150,
        }),
        columnHelper.display({
            id: 'actions',
            header: __('Actions', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const isGenerating = generatingAI.has(rowId);

                return (
                    <div className="action-buttons">
                        <Button
                            variant="secondary"
                            size="small"
                            onClick={() => generateAI(rowId)}
                            disabled={isGenerating}
                        >
                            {isGenerating ? <Spinner /> : __('Generate AI', 'localseo-booster')}
                        </Button>
                        <Button
                            variant="tertiary"
                            size="small"
                            isDestructive
                            onClick={() => deleteRow(rowId)}
                        >
                            {__('Delete', 'localseo-booster')}
                        </Button>
                    </div>
                );
            },
            size: 180,
        }),
    ], [editingCell, generatingAI, data]);

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    if (loading && data.length === 0) {
        return (
            <div className="localseo-loading">
                <Spinner />
                <p>{__('Loading data...', 'localseo-booster')}</p>
            </div>
        );
    }

    return (
        <div className="localseo-data-center">
            <h1>{__('LocalSEO Data Center', 'localseo-booster')}</h1>
            
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError(null)}>
                    {error}
                </Notice>
            )}

            <div className="toolbar">
                <Button variant="primary" onClick={addRow}>
                    {__('Add New Row', 'localseo-booster')}
                </Button>
                <Button variant="secondary" onClick={bulkGenerateAI}>
                    {__('Generate All Missing AI Fields', 'localseo-booster')}
                </Button>
                <Button variant="tertiary" onClick={fetchData}>
                    {__('Refresh', 'localseo-booster')}
                </Button>
            </div>

            <div className="table-container">
                <table className="localseo-table">
                    <thead>
                        {table.getHeaderGroups().map(headerGroup => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map(header => (
                                    <th
                                        key={header.id}
                                        style={{ width: header.getSize() }}
                                    >
                                        {flexRender(
                                            header.column.columnDef.header,
                                            header.getContext()
                                        )}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.map(row => (
                            <tr key={row.id}>
                                {row.getVisibleCells().map(cell => (
                                    <td
                                        key={cell.id}
                                        style={{ width: cell.column.getSize() }}
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext()
                                        )}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {data.length === 0 && (
                <div className="empty-state">
                    <p>{__('No data yet. Click "Add New Row" to get started.', 'localseo-booster')}</p>
                </div>
            )}
        </div>
    );
};

export default DataCenter;
